import { NextRequest, NextResponse } from 'next/server';
import pool from '../../../../lib/db';

export async function GET(req: NextRequest) {
  const { searchParams } = new URL(req.url);
  const mapId = searchParams.get('map_id');
  if (!mapId) {
    return NextResponse.json({ error: 'map_id is required' }, { status: 400 });
  }

  try {
    const [mapRows]: any = await pool.execute('SELECT * FROM maps WHERE id = ?', [mapId]);
    if (mapRows.length === 0) {
      return NextResponse.json({ error: 'Map not found' }, { status: 404 });
    }

    const [devices]: any = await pool.execute(`
      SELECT 
        d.id, d.name, d.ip, d.check_port, d.type, d.subchoice, d.description, d.x, d.y, 
        d.ping_interval, d.icon_size, d.name_text_size, d.icon_url, 
        d.warning_latency_threshold, d.warning_packetloss_threshold, 
        d.critical_latency_threshold, d.critical_packetloss_threshold, 
        d.show_live_ping, d.status, d.last_seen, d.last_avg_time, d.last_ttl,
        hm.cpu_usage, hm.memory_usage, hm.disk_usage, hm.network_in, hm.network_out
      FROM devices d
      LEFT JOIN host_metrics hm ON (
        (d.ip IS NOT NULL AND d.ip != '' AND hm.ip_address = d.ip) OR 
        (d.name IS NOT NULL AND d.name != '' AND hm.hostname = d.name)
      )
      WHERE d.map_id = ?
    `, [mapId]);

    const [edges]: any = await pool.execute('SELECT id, source_id, target_id, connection_type FROM device_edges WHERE map_id = ?', [mapId]);

    return NextResponse.json({
      map: mapRows[0],
      devices,
      edges
    });
  } catch (err: any) {
    return NextResponse.json({ error: err.message }, { status: 500 });
  }
}
export const dynamic = 'force-dynamic';
