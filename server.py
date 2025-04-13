from http.server import HTTPServer, BaseHTTPRequestHandler
import json
import os

class DBHandler(BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path == '/':
            # Serviere die index.html
            self.send_response(200)
            self.send_header('Content-type', 'text/html')
            self.end_headers()
            with open('index.html', 'rb') as file:
                self.wfile.write(file.read())
        elif self.path == '/api/data':
            # Lese die db.txt
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.send_header('Access-Control-Allow-Origin', '*')
            self.end_headers()
            with open('db.txt', 'r', encoding='utf-8') as file:
                data = file.read()
                self.wfile.write(json.dumps({'data': data}).encode())

    def do_POST(self):
        if self.path == '/api/data':
            # Lese die POST-Daten
            content_length = int(self.headers['Content-Length'])
            post_data = self.rfile.read(content_length)
            data = json.loads(post_data.decode())
            
            # Speichere die Daten in db.txt
            with open('db.txt', 'w', encoding='utf-8') as file:
                file.write(data['data'])
            
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.send_header('Access-Control-Allow-Origin', '*')
            self.end_headers()
            self.wfile.write(json.dumps({'status': 'success'}).encode())

    def do_OPTIONS(self):
        # Handle CORS preflight requests
        self.send_response(200)
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type')
        self.end_headers()

if __name__ == '__main__':
    server = HTTPServer(('localhost', 8000), DBHandler)
    print('Server läuft auf http://localhost:8000')
    server.serve_forever() 