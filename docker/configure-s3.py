import json
import os
import sys
from pathlib import Path
from urllib.parse import urlparse


def configure(env_path, credential_path, bucket):
    credentials = json.loads(Path(credential_path).read_text(encoding="utf-8"))
    required = ("accessKeyId", "secretAccessKey", "endpoint", "region")
    if not all(isinstance(credentials.get(key), str) and credentials[key] for key in required):
        raise ValueError("Incomplete S3 credential file")
    endpoint = credentials["endpoint"].rstrip("/")
    parsed = urlparse(endpoint)
    if parsed.scheme != "https" or not parsed.netloc:
        raise ValueError("S3 endpoint must be HTTPS")
    if not bucket or any(character not in "abcdefghijklmnopqrstuvwxyz0123456789-." for character in bucket):
        raise ValueError("Invalid S3 bucket name")
    updates = {
        "S3_ENDPOINT": endpoint,
        "S3_PUBLIC_URL": endpoint,
        "S3_REGION": credentials["region"],
        "S3_BUCKET": bucket,
        "S3_ACCESS_KEY": credentials["accessKeyId"],
        "S3_SECRET_KEY": credentials["secretAccessKey"],
        "S3_PATH_STYLE": "true",
    }
    lines = Path(env_path).read_text(encoding="utf-8").splitlines()
    found = set()
    output = []
    for line in lines:
        name = line.split("=", 1)[0] if "=" in line else ""
        if name in updates:
            output.append(f"{name}={updates[name]}")
            found.add(name)
        else:
            output.append(line)
    output.extend(f"{name}={value}" for name, value in updates.items() if name not in found)
    Path(env_path).write_text("\n".join(output) + "\n", encoding="utf-8")
    os.chmod(env_path, 0o600)


if __name__ == "__main__":
    if len(sys.argv) != 4:
        raise SystemExit("Usage: configure-s3.py ENV CREDENTIAL_JSON BUCKET")
    configure(*sys.argv[1:])
    print("S3 environment configured.")
