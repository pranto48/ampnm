import { NextResponse } from "next/server";
import crypto from "crypto";

const ENCRYPTION_KEY = "ITSupportBD_SecureKey_2024";
// PHP backend that handles MySQL-based license verification
const PHP_VERIFY_URL = "https://portal.itsupport.com.bd/verify_license.php";

/**
 * Encrypts license data in the format expected by the PHP app (AES-256-CBC).
 */
function encryptLicenseResponse(data: any): string {
  const keyBuffer = Buffer.alloc(32);
  keyBuffer.write(ENCRYPTION_KEY, "utf-8");
  const iv = crypto.randomBytes(16);
  const cipher = crypto.createCipheriv("aes-256-cbc", keyBuffer, iv);
  let encrypted = cipher.update(JSON.stringify(data), "utf8", "base64");
  encrypted += cipher.final("base64");
  const combined = Buffer.concat([iv, Buffer.from(encrypted, "utf-8")]);
  return combined.toString("base64");
}

/**
 * Extracts client IP from request headers.
 */
function getClientIp(request: Request): string {
  const headers = new Headers(request.headers);
  const forwarded = headers.get("x-forwarded-for");
  if (forwarded) return forwarded.split(",")[0].trim();
  return headers.get("x-real-ip") || headers.get("cf-connecting-ip") || "unknown";
}

// Handle GET /api/license/verify
export async function GET(request: Request) {
  try {
    const { searchParams } = new URL(request.url);
    const key = searchParams.get("key") || searchParams.get("app_license_key");
    const isPhpClient = searchParams.has("app_license_key");
    const installationId = searchParams.get("installation_id") || "";
    const currentDeviceCount = parseInt(searchParams.get("current_device_count") || "0", 10);
    const userId = searchParams.get("user_id") || "anonymous";

    if (!key) {
      const errorData = { success: false, message: "License key is required", actual_status: "invalid_request" };
      if (isPhpClient) return new Response(encryptLicenseResponse(errorData), { headers: { "Content-Type": "text/plain" } });
      return NextResponse.json({ valid: false, error: "License key is required" }, { status: 400 });
    }
    return await verifyCore(key, { isPhpClient, installationId, currentDeviceCount, userId }, request);
  } catch (error: any) {
    console.error("GET verify error:", error);
    return NextResponse.json({ valid: false, error: "Internal Server Error" }, { status: 500 });
  }
}

// Handle POST /api/license/verify
export async function POST(request: Request) {
  try {
    const body = await request.json().catch(() => ({}));
    const key = body.key || body.app_license_key;
    const isPhpClient = "app_license_key" in body;
    const installationId = body.installation_id || "";
    const currentDeviceCount = parseInt(body.current_device_count || "0", 10);
    const userId = body.user_id || "anonymous";

    if (!key) {
      const errorData = { success: false, message: "License key is required", actual_status: "invalid_request" };
      if (isPhpClient) return new Response(encryptLicenseResponse(errorData), { headers: { "Content-Type": "text/plain" } });
      return NextResponse.json({ valid: false, error: "License key is required" }, { status: 400 });
    }
    return await verifyCore(key, { isPhpClient, installationId, currentDeviceCount, userId }, request);
  } catch (error: any) {
    console.error("POST verify error:", error);
    return NextResponse.json({ valid: false, error: "Internal Server Error" }, { status: 500 });
  }
}

// Core verification — proxies to PHP backend which uses MySQL
async function verifyCore(
  key: string,
  params: { isPhpClient: boolean; installationId: string; currentDeviceCount: number; userId: string },
  request: Request
) {
  const { isPhpClient, installationId, currentDeviceCount, userId } = params;

  // Proxy the request to the PHP backend
  let phpResponse: Response;
  try {
    phpResponse = await fetch(PHP_VERIFY_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        app_license_key: key,
        user_id: userId || "anonymous",
        installation_id: installationId,
        current_device_count: currentDeviceCount,
      }),
      signal: AbortSignal.timeout(10000),
    });
  } catch (e: any) {
    console.error("PHP backend unreachable:", e.message);
    const errData = { success: false, message: "License server is temporarily unavailable.", actual_status: "error" };
    if (isPhpClient) return new Response(encryptLicenseResponse(errData), { headers: { "Content-Type": "text/plain" } });
    return NextResponse.json({ valid: false, error: "License server unavailable" }, { status: 503 });
  }

  const encryptedResult = await phpResponse.text();

  // If the request came from the PHP AmPOS client, pass through the encrypted response directly
  if (isPhpClient) {
    return new Response(encryptedResult.trim(), { headers: { "Content-Type": "text/plain" } });
  }

  // For browser/dashboard clients, decrypt and return JSON
  try {
    const keyBuffer = Buffer.alloc(32);
    keyBuffer.write(ENCRYPTION_KEY, "utf-8");
    const data = Buffer.from(encryptedResult.trim(), "base64");
    const iv = data.subarray(0, 16);
    const encBytes = data.subarray(16);
    const decipher = crypto.createDecipheriv("aes-256-cbc", keyBuffer, iv);
    let decrypted = decipher.update(encBytes.toString("base64"), "base64", "utf8");
    decrypted += decipher.final("utf8");
    const parsed = JSON.parse(decrypted);

    if (parsed.success) {
      return NextResponse.json({
        valid: true,
        status: parsed.actual_status || "active",
        expiresAt: parsed.expires_at,
        maxDevices: parsed.max_devices,
        message: parsed.message,
      });
    } else {
      return NextResponse.json({
        valid: false,
        status: parsed.actual_status || "invalid",
        error: parsed.message,
      }, { status: 400 });
    }
  } catch (e) {
    console.error("Failed to decrypt PHP response:", e);
    return NextResponse.json({ valid: false, error: "Invalid license server response" }, { status: 502 });
  }
}
