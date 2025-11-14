import os
import time
from http.server import BaseHTTPRequestHandler, HTTPServer


BANNER = os.getenv("DEMO_BUILD_MESSAGE", "startContainer demo is running")


class DemoHandler(BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path == "/health":
            self.send_response(200)
            self.send_header("Content-Type", "text/plain; charset=utf-8")
            self.end_headers()
            self.wfile.write(b"ok\n")
            return

        message = f"<h1>{BANNER}</h1><p>Request path: {self.path}</p>"
        payload = message.encode("utf-8")
        self.send_response(200)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.send_header("Content-Length", str(len(payload)))
        self.end_headers()
        self.wfile.write(payload)

    def log_message(self, fmt, *args):
        # Keep the output focused on build/start progress
        return


def main() -> None:
    port = 8080
    server = HTTPServer(("0.0.0.0", port), DemoHandler)
    print(f"[runtime] Demo HTTP server is listening on port {port}")
    server.serve_forever()


if __name__ == "__main__":
    for seconds_left in range(3, 0, -1):
        print(f"[runtime] Bootstrapping services in {seconds_left}...")
        time.sleep(1)
    main()
