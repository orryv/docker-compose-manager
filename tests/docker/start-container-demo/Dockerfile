# syntax=docker/dockerfile:1
FROM python:3.11-slim

ARG DEMO_BUILD_MESSAGE="Preparing startContainer visual test"

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl ca-certificates \
    && rm -rf /var/lib/apt/lists/*

RUN python - <<'PY'
import time
for step in range(1, 6):
    print(f"[build] Simulating data preparation step {step}/5")
    time.sleep(1)
PY

RUN /bin/sh -c "set -eu; echo '[build] Creating demo directories'; for item in cache config data logs public scripts; do echo \"  - generating ${item}\"; mkdir -p /opt/demo/${item}; sleep 0.5; done"

RUN python - <<'PY'
from pathlib import Path
content = "\n".join([
    "# startContainer visual test",
    "This file proves that the layered build finished successfully.",
])
Path('/opt/demo/README.txt').write_text(content)
PY

COPY demo_app.py /opt/demo/app.py

RUN python - <<'PY'
from pathlib import Path
msg = Path('/opt/demo/app.py').read_text().splitlines()[0]
Path('/opt/demo/build-message.txt').write_text('Build completed with banner: ' + msg + '\n')
PY

EXPOSE 8080

ENV DEMO_BUILD_MESSAGE=${DEMO_BUILD_MESSAGE}

CMD ["python", "/opt/demo/app.py"]
