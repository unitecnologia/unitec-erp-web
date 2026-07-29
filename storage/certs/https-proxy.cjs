const https = require("https");
const http = require("http");
const fs = require("fs");
const { URL } = require("url");

const SOURCE = Number(process.env.HTTPS_PORT || 8443);
const TARGET = Number(process.env.HTTP_PORT || 8000);
const KEY = process.env.SSL_KEY;
const CERT = process.env.SSL_CERT;

const options = {
  key: fs.readFileSync(KEY),
  cert: fs.readFileSync(CERT),
};

const server = https.createServer(options, (req, res) => {
  const headers = { ...req.headers, host: `127.0.0.1:${TARGET}` };
  const proxyReq = http.request(
    {
      hostname: "127.0.0.1",
      port: TARGET,
      path: req.url,
      method: req.method,
      headers,
    },
    (proxyRes) => {
      res.writeHead(proxyRes.statusCode || 502, proxyRes.headers);
      proxyRes.pipe(res);
    }
  );
  proxyReq.on("error", (err) => {
    console.error("proxy error", err.message);
    if (!res.headersSent) res.writeHead(502, { "Content-Type": "text/plain" });
    res.end("Bad gateway: " + err.message);
  });
  req.pipe(proxyReq);
});

server.on("tlsClientError", (err) => console.error("tls", err.message));
server.listen(SOURCE, "0.0.0.0", () => {
  console.log(`HTTPS proxy listening on 0.0.0.0:${SOURCE} -> 127.0.0.1:${TARGET}`);
});
