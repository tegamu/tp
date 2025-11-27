import os
import json
import time
import uuid

import boto3
import pymysql

DB_CONFIG = {
    "host": os.environ["DB_HOST"],
    "user": os.environ["DB_USER"],
    "password": os.environ["DB_PASSWORD"],
    "db": os.environ["DB_NAME"],
    "port": int(os.environ.get("DB_PORT", "3306")),
    "charset": "utf8mb4",
    "cursorclass": pymysql.cursors.DictCursor,
    "autocommit": True,
}

EC2_REGION = os.environ.get("EC2_REGION", "ap-northeast-2")

GAME_AMI_ID = os.environ["GAME_AMI_ID"]
GAME_INSTANCE_TYPE = os.environ["GAME_INSTANCE_TYPE"]
GAME_KEY_NAME = os.environ.get("GAME_KEY_NAME")

GAME_SG_ID = os.environ["GAME_SG_ID"]
GAME_SUBNET_ID = os.environ["GAME_SUBNET_ID"]

SM_BROKER_HOST = os.environ["SM_BROKER_HOST"]
DCV_GATEWAY_HOST = os.environ.get("DCV_GATEWAY_HOST")


def _get_db_conn():
    return pymysql.connect(**DB_CONFIG)


def _response(status: int, body: dict) -> dict:
    return {
        "statusCode": status,
        "headers": {"Content-Type": "application/json"},
        "body": json.dumps(body),
    }


def _get_cookie_one(cookie_str: str, name: str) -> str | None:
    parts = [p.strip() for p in cookie_str.split(";") if p.strip()]
    prefix = name + "="
    for part in parts:
        if part.startswith(prefix):
            return part[len(prefix) :]
    return None


def _extract_login_session_id(event: dict) -> str | None:
    headers = {k.lower(): v for k, v in (event.get("headers") or {}).items()}

    cookie_header = headers.get("cookie")
    if cookie_header:
        sid = _get_cookie_one(cookie_header, "sessionId")
        if sid:
            return sid

    for c in event.get("cookies") or []:
        sid = _get_cookie_one(c, "sessionId")
        if sid:
            return sid

    return None


DCV_USER_DATA_TEMPLATE = """#!/bin/bash
set -xe

DCVCONF="/etc/dcv/dcv.conf"
if [ -f "$DCVCONF" ]; then
  cp "$DCVCONF" "${DCVCONF}.bak" || true
fi

cat > "$DCVCONF" << 'DCVCONF_EOF'
[session-management/defaults]

[session-management/automatic-console-session]
owner = "ubuntu"

[display]
web-display = true

[connectivity]
web-port = 8443

[security]
authentication = "none"
DCVCONF_EOF

systemctl enable dcvserver || true
systemctl restart dcvserver || systemctl start dcvserver || true

AGENTCONF="/etc/dcv-session-manager-agent/agent.conf"
if [ -f "$AGENTCONF" ]; then
  cp "$AGENTCONF" "${AGENTCONF}.bak" || true
fi

mkdir -p /etc/dcv-session-manager-agent
cat > "$AGENTCONF" << 'AGENTCONF_EOF'
[agent]
broker_host = "{BROKER_HOST}"
broker_port = 8445

[security]
tls_strict = false
AGENTCONF_EOF

systemctl enable dcv-session-manager-agent || true
systemctl restart dcv-session-manager-agent || systemctl start dcv-session-manager-agent || true
"""


def _create_game_instance(user_id: str) -> dict:
    ec2 = boto3.client("ec2", region_name=EC2_REGION)

    session_id = f"sess-{int(time.time() * 1000)}-{uuid.uuid4().hex[:6]}"
    print("[game] creating EC2 for session:", session_id, "user:", user_id)

    user_data_script = DCV_USER_DATA_TEMPLATE.format(
        BROKER_HOST=SM_BROKER_HOST,
    )

    run_args = {
        "ImageId": GAME_AMI_ID,
        "InstanceType": GAME_INSTANCE_TYPE,
        "MinCount": 1,
        "MaxCount": 1,
        "NetworkInterfaces": [
            {
                "DeviceIndex": 0,
                "SubnetId": GAME_SUBNET_ID,
                "Groups": [GAME_SG_ID],
                "AssociatePublicIpAddress": True,
            }
        ],
        "UserData": user_data_script,
        "TagSpecifications": [
            {
                "ResourceType": "instance",
                "Tags": [
                    {"Key": "Name", "Value": f"game-session-{session_id}"},
                    {"Key": "GameSessionId", "Value": session_id},
                    {"Key": "GameUserId", "Value": user_id},
                ],
            }
        ],
    }

    if GAME_KEY_NAME:
        run_args["KeyName"] = GAME_KEY_NAME

    resp = ec2.run_instances(**run_args)
    instance = resp["Instances"][0]
    instance_id = instance["InstanceId"]

    print("[game] EC2 instance created:", instance_id)

    with _get_db_conn() as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO game_sessions (session_id, user_id, instance_id, status)
                VALUES (%s, %s, %s, %s)
                """,
                (session_id, user_id, instance_id, "PENDING"),
            )

    return {
        "sessionId": session_id,
        "status": "PENDING",
        "instanceId": instance_id,
    }


def _handler_impl(event, context):
    print("[game] event:", json.dumps(event)[:1000])

    login_session_id = _extract_login_session_id(event)
    if not login_session_id:
        print("[game] no login sessionId cookie")
        return _response(401, {"error": "unauthorized", "detail": "login sessionId cookie missing"})

    # ★ 여기만 ms → sec 로 바뀐 부분
    now_sec = int(time.time())

    row = None
    try:
        with _get_db_conn() as conn:
            with conn.cursor() as cur:
                cur.execute(
                    """
                    SELECT user_id, expires_at, status
                    FROM sessions
                    WHERE session_id = %s
                    """,
                    (login_session_id,),
                )
                row = cur.fetchone()
    except Exception as e:
        print("[game] ERROR loading login session:", repr(e))
        return _response(500, {"error": "internal", "detail": "failed to load login session"})

    if not row:
        print("[game] login session not found:", login_session_id)
        return _response(401, {"error": "unauthorized", "detail": "session not found"})

    if row["status"] != "active":
        print("[game] login session not active:", row["status"])
        return _response(401, {"error": "unauthorized", "detail": "session not active"})

    if row["expires_at"] <= now_sec:
        print("[game] login session expired:", row["expires_at"], "<=", now_sec)
        return _response(401, {"error": "unauthorized", "detail": "session expired"})

    user_id = row["user_id"]

    raw_body = event.get("body") or ""
    if event.get("isBase64Encoded"):
        import base64
        raw_body = base64.b64decode(raw_body).decode("utf-8")

    try:
        body = json.loads(raw_body) if raw_body else {}
    except json.JSONDecodeError:
        body = {}

    game_id = body.get("gameId") or body.get("game_id") or "default"
    region = body.get("region") or EC2_REGION

    print(f"[game] user_id={user_id}, game_id={game_id}, region={region}")

    result = _create_game_instance(user_id=user_id)
    result.update({"gameId": game_id, "region": region})

    return _response(200, result)


def handler(event, context):
    try:
        return _handler_impl(event, context)
    except Exception as e:
        print("[game] UNEXPECTED ERROR:", repr(e))
        return _response(500, {"error": "internal", "detail": str(e)})
