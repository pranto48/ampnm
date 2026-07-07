import { WebSocketServer, WebSocket } from 'ws';
import type { NextRequest } from 'next/server';

// Persist the WebSocket server across Next.js dev hot-reloads
let wss = (global as any).wss as WebSocketServer;

if (!wss) {
  wss = new WebSocketServer({ port: 8080 });
  (global as any).wss = wss;
  console.log('WebSocket Gateway Server initialized on port 8080');

  wss.on('connection', (ws: WebSocket) => {
    console.log('Client connected to Next.js WebSocket Gateway');

    ws.on('message', (message: any) => {
      try {
        const dataStr = message.toString();
        // Broadcast to all OTHER connected clients
        wss.clients.forEach((client) => {
          if (client !== ws && client.readyState === WebSocket.OPEN) {
            client.send(dataStr);
          }
        });
      } catch (e) {
        console.error('WebSocket message parsing error:', e);
      }
    });

    ws.on('error', (err) => {
      console.error('WebSocket error:', err);
    });

    ws.on('close', () => {
      console.log('Client disconnected from Next.js WebSocket Gateway');
    });
  });
}

export async function GET(req: NextRequest) {
  return new Response(JSON.stringify({ success: true, status: 'WebSocket Server Running', port: 8080 }), {
    status: 200,
    headers: { 'Content-Type': 'application/json' },
  });
}
export const dynamic = 'force-dynamic';
