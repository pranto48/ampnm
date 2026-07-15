import { NextResponse } from "next/server";
import crypto from "crypto";

const ENCRYPTION_KEY = "ITSupportBD_SecureKey_2024";

// ─── Admin/Static Licenses ────────────────────────────────────────────────────
// Admin-created licenses that don't require a database lookup.
// These are provisioned directly here for admin installations.
const STATIC_LICENSES: Record<string, {
  status: string;
  expiresAt: string;
  maxDevices: number;
  orgId: string;
  productId: string;
}> = {
  "AMP256-B713A3E37B5FE53C-6F38AE70F5CC0DF6-EBB7A8AEFFB261B0-9401F60994D97223": {
    status: "active",
    expiresAt: "2027-12-31",
    maxDevices: 1,
    orgId: "admin-global",
    productId: "prod-ampos",
  },
};

// In-memory binding store for static licenses (resets on cold-start but good enough for single-server)
const BINDING_STORE: Record<string, string> = {};

/**
 * Encrypts license data in AES-256-CBC format expected by the PHP AmPOS client.
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

// Core Verification Logic
async function verifyCore(
  key: string,
  params: { isPhpClient: boolean; installationId: string; currentDeviceCount: number; userId: string },
  request: Request
) {
  const { isPhpClient, installationId } = params;

  // 1. Check static/admin licenses first
  const staticLicense = STATIC_LICENSES[key];
  if (staticLicense) {
    const now = new Date();
    const expiresAt = new Date(staticLicense.expiresAt);

    if (staticLicense.status !== "active" && staticLicense.status !== "free") {
      const errData = { success: false, message: `License is ${staticLicense.status}.`, actual_status: staticLicense.status };
      if (isPhpClient) return new Response(encryptLicenseResponse(errData), { headers: { "Content-Type": "text/plain" } });
      return NextResponse.json({ valid: false, status: staticLicense.status, error: `License is ${staticLicense.status}` });
    }

    if (expiresAt < now) {
      const errData = { success: false, message: "License has expired.", actual_status: "expired" };
      if (isPhpClient) return new Response(encryptLicenseResponse(errData), { headers: { "Content-Type": "text/plain" } });
      return NextResponse.json({ valid: false, status: "expired", error: "License has expired." });
    }

    // Binding check (in-memory)
    if (installationId) {
      const boundId = BINDING_STORE[key];
      if (!boundId) {
        BINDING_STORE[key] = installationId;
      } else if (boundId !== installationId) {
        const errData = { success: false, message: "License is already in use by another server.", actual_status: "in_use" };
        if (isPhpClient) return new Response(encryptLicenseResponse(errData), { headers: { "Content-Type": "text/plain" } });
        return NextResponse.json({ valid: false, status: "in_use", error: "License already in use by another server." }, { status: 409 });
      }
    }

    if (isPhpClient) {
      return new Response(
        encryptLicenseResponse({
          success: true,
          message: "License is active.",
          max_devices: staticLicense.maxDevices,
          actual_status: staticLicense.status,
          expires_at: staticLicense.expiresAt,
          core_key: "ITSupportBD_CoreShield_2026",
        }),
        { headers: { "Content-Type": "text/plain" } }
      );
    }

    return NextResponse.json({
      valid: true,
      status: staticLicense.status,
      expiresAt: staticLicense.expiresAt,
      orgId: staticLicense.orgId,
      productId: staticLicense.productId,
      lastIp: getClientIp(request),
      lastVerifiedAt: new Date().toISOString(),
    });
  }

  // 2. Key not found in static list
  const errorData = {
    success: false,
    message: "Invalid or unregistered application license key.",
    actual_status: "not_found",
  };
  if (isPhpClient) {
    return new Response(encryptLicenseResponse(errorData), { headers: { "Content-Type": "text/plain" } });
  }
  return NextResponse.json(
    { valid: false, status: "not_found", error: "License key not registered in system database" },
    { status: 404 }
  );
}
