import { NextResponse } from "next/server";
import { getAdminDb } from "@/lib/firebase-admin";
import crypto from "crypto";

const ENCRYPTION_KEY = "ITSupportBD_SecureKey_2024";

/**
 * Encrypts license data in the double-base64 format expected by the PHP app.
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
  if (forwarded) {
    const firstIp = forwarded.split(",")[0].trim();
    if (firstIp) return firstIp;
  }
  const realIp = headers.get("x-real-ip");
  if (realIp) return realIp.trim();
  const cfIp = headers.get("cf-connecting-ip");
  if (cfIp) return cfIp.trim();
  return "unknown";
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
      if (isPhpClient) {
        return new Response(encryptLicenseResponse(errorData), { headers: { "Content-Type": "text/plain" } });
      }
      return NextResponse.json({ valid: false, error: "License key is required" }, { status: 400 });
    }

    return await verifyCore(key, { isPhpClient, installationId, currentDeviceCount, userId }, request);
  } catch (error: any) {
    console.error("API error during GET license verification:", error);
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
      if (isPhpClient) {
        return new Response(encryptLicenseResponse(errorData), { headers: { "Content-Type": "text/plain" } });
      }
      return NextResponse.json({ valid: false, error: "License key parameter 'key' or 'app_license_key' is required" }, { status: 400 });
    }

    return await verifyCore(key, { isPhpClient, installationId, currentDeviceCount, userId }, request);
  } catch (error: any) {
    console.error("API error during POST license verification:", error);
    const isPhpClient = request.headers.get("content-type")?.includes("json") === false; // fallback check
    if (isPhpClient) {
      return new Response(encryptLicenseResponse({ success: false, message: "Internal server error", actual_status: "error" }), { headers: { "Content-Type": "text/plain" } });
    }
    return NextResponse.json({ valid: false, error: "Internal Server Error" }, { status: 500 });
  }
}

// Core Verification Logic
async function verifyCore(
  key: string,
  params: { isPhpClient: boolean; installationId: string; currentDeviceCount: number; userId: string },
  request: Request
) {
  const { isPhpClient, installationId, currentDeviceCount } = params;
  const adminDb = getAdminDb();
  const licensesRef = adminDb.collection("licenses");
  const snapshot = await licensesRef.where("key", "==", key).limit(1).get();

  if (snapshot.empty) {
    const errorData = {
      success: false,
      message: "Invalid or unregistered application license key.",
      actual_status: "not_found"
    };
    if (isPhpClient) {
      return new Response(encryptLicenseResponse(errorData), { headers: { "Content-Type": "text/plain" } });
    }
    return NextResponse.json(
      { valid: false, status: "not_found", error: "License key not registered in system database" },
      { status: 404 }
    );
  }

  const doc = snapshot.docs[0];
  const licenseData = doc.data();

  // Expiration boundary check
  const now = new Date();
  const expiresAt = new Date(licenseData.expiresAt);

  if (licenseData.status === "active" && expiresAt < now) {
    await doc.ref.update({ status: "expired" });
    licenseData.status = "expired";
  }

  // State / Status validation
  if (licenseData.status !== "active" && licenseData.status !== "free") {
    const errorData = {
      success: false,
      message: `License is ${licenseData.status}.`,
      actual_status: licenseData.status
    };
    if (isPhpClient) {
      return new Response(encryptLicenseResponse(errorData), { headers: { "Content-Type": "text/plain" } });
    }
    return NextResponse.json({
      valid: false,
      status: licenseData.status,
      error: `License key status is currently '${licenseData.status}'`,
      expiresAt: licenseData.expiresAt,
    });
  }

  // Bound installation ID verification (Multi-server lockout prevention)
  if (installationId) {
    if (!licenseData.boundInstallationId) {
      // Bind to this installation ID
      await doc.ref.update({ boundInstallationId: installationId });
      licenseData.boundInstallationId = installationId;
    } else if (licenseData.boundInstallationId !== installationId) {
      const errorData = {
        success: false,
        message: "License is already in use by another server.",
        actual_status: "in_use"
      };
      if (isPhpClient) {
        return new Response(encryptLicenseResponse(errorData), { headers: { "Content-Type": "text/plain" } });
      }
      return NextResponse.json({
        valid: false,
        status: "in_use",
        error: "License is already in use by another server.",
      }, { status: 409 });
    }
  }

  // Update last seen IP and verification timestamps
  const clientIp = getClientIp(request);
  const verifiedAt = new Date().toISOString();

  try {
    await doc.ref.update({
      lastIp: clientIp,
      lastVerifiedAt: verifiedAt,
      currentDevices: currentDeviceCount,
    });
  } catch (e) {
    console.warn("Failed to update license metadata:", e);
  }

  // Determine max devices allocation based on product package
  let maxDevices = 1;
  if (licenseData.productId === "prod-cluster") {
    maxDevices = 10;
  } else if (licenseData.productId === "prod-enterprise") {
    maxDevices = 9999;
  } else if (licenseData.productId === "prod-ampos") {
    maxDevices = 1; // 1 device active limit for standard AmPOS license
  }

  if (isPhpClient) {
    return new Response(
      encryptLicenseResponse({
        success: true,
        message: "License is active.",
        max_devices: maxDevices,
        actual_status: licenseData.status,
        expiresAt: licenseData.expiresAt,
        core_key: "ITSupportBD_CoreShield_2026"
      }),
      { headers: { "Content-Type": "text/plain" } }
    );
  }

  // Fetch target corporate client/organization metadata for JSON client
  let orgName = "Unknown Client";
  try {
    const orgSnap = await adminDb.collection("organizations").doc(licenseData.orgId).get();
    if (orgSnap.exists) {
      orgName = orgSnap.data()?.name || "Unknown Client";
    }
  } catch (e) {
    console.warn("Failed to fetch organization details:", e);
  }

  return NextResponse.json({
    valid: true,
    status: licenseData.status,
    expiresAt: licenseData.expiresAt,
    orgId: licenseData.orgId,
    orgName: orgName,
    productId: licenseData.productId,
    lastIp: clientIp,
    lastVerifiedAt: verifiedAt,
  });
}
