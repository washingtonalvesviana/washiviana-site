#!/usr/bin/env python3
"""Servidor RSS de teste (localhost) para o teste de radar collect."""
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

HOST = "127.0.0.1"
PORT = 8092

RSS = """<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel>
<title>Mock Feed</title>
<item>
  <title>ZZ Item Um</title>
  <link>http://127.0.0.1:8092/a?utm_source=x&amp;ref=y</link>
  <description>&lt;p&gt;Descrição &lt;b&gt;um&lt;/b&gt;&lt;/p&gt;</description>
  <pubDate>Mon, 28 Sep 2026 12:00:00 +0000</pubDate>
  <guid>zz-1</guid>
</item>
<item>
  <title>ZZ Item Dois</title>
  <link>http://127.0.0.1:8092/b</link>
  <description>Descrição dois</description>
  <pubDate>Sun, 27 Sep 2026 12:00:00 +0000</pubDate>
  <guid>zz-2</guid>
</item>
</channel></rss>"""


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        body = RSS.encode()
        self.send_response(200)
        self.send_header("Content-Type", "application/rss+xml; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, *args):
        pass


if __name__ == "__main__":
    ThreadingHTTPServer((HOST, PORT), Handler).serve_forever()
