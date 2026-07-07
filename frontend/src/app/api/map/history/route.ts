import { NextRequest } from 'next/server';
import pool from '../../../../lib/db';

export async function GET(req: NextRequest) {
  const { searchParams } = new URL(req.url);
  const timestampParam = searchParams.get('timestamp');
  const mapIdParam = searchParams.get('map_id');

  if (!timestampParam) {
    return new Response(JSON.stringify({ error: 'timestamp parameter is required' }), {
      status: 400,
      headers: { 'Content-Type': 'application/json' },
    });
  }

  // Parse timestamp (supports Unix seconds/milliseconds and ISO strings)
  let queryDate: Date;
  if (/^\d+$/.test(timestampParam)) {
    const num = parseInt(timestampParam, 10);
    // If it's larger than 100000000000, it's likely milliseconds
    if (num > 100000000000) {
      queryDate = new Date(num);
    } else {
      queryDate = new Date(num * 1000);
    }
  } else {
    queryDate = new Date(timestampParam);
  }

  if (isNaN(queryDate.getTime())) {
    return new Response(JSON.stringify({ error: 'Invalid timestamp format' }), {
      status: 400,
      headers: { 'Content-Type': 'application/json' },
    });
  }

  // Convert Date to MySQL format 'YYYY-MM-DD HH:MM:SS.ffffff'
  const mysqlDateTime = queryDate.toISOString().slice(0, 19).replace('T', ' ');

  try {
    let query = '';
    let params: any[] = [];

    if (mapIdParam) {
      query = `
        SELECT 
          d.id,
          d.name,
          d.ip,
          d.map_id,
          COALESCE(
            (
              SELECT status 
              FROM device_status_logs 
              WHERE device_id = d.id AND created_at <= ?
              ORDER BY created_at DESC, id DESC
              LIMIT 1
            ),
            'unknown'
          ) as status
        FROM devices d
        WHERE d.map_id = ?
      `;
      params = [mysqlDateTime, mapIdParam];
    } else {
      query = `
        SELECT 
          d.id,
          d.name,
          d.ip,
          d.map_id,
          COALESCE(
            (
              SELECT status 
              FROM device_status_logs 
              WHERE device_id = d.id AND created_at <= ?
              ORDER BY created_at DESC, id DESC
              LIMIT 1
            ),
            'unknown'
          ) as status
        FROM devices d
      `;
      params = [mysqlDateTime];
    }

    const [rows] = await pool.execute(query, params);
    
    return new Response(JSON.stringify(rows), {
      status: 200,
      headers: { 
        'Content-Type': 'application/json',
        'Cache-Control': 'no-store, max-age=0'
      },
    });

  } catch (err: any) {
    console.error('History API error:', err);
    return new Response(JSON.stringify({ error: 'Database error', details: err.message }), {
      status: 500,
      headers: { 'Content-Type': 'application/json' },
    });
  }
}
export const dynamic = 'force-dynamic';
