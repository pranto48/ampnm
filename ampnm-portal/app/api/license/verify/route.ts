import { NextResponse } from "next/server";
import { getAdminDb } from "@/lib/firebase-admin";

/**
 * Extracts client IP from request headers.
 * Handles reverse-proxy chains (Vercel, Cloudflare, etc.)
 */
function getClientIp(request: Request): string {
  const headers = new Headers(request.headers);
  
  // x-forwarded-for can contain comma-separated list; first entry is client
  const forwarded = headers.get("x-forwarded-for");
  if (forwarded) {
    const firstIp = forwarded.split(",")[0].trim();
    if (firstIp) return firstIp;
  }

  // x-real-ip is set by some proxies (nginx, etc.)
  const realIp = headers.get("x-real-ip");
  if (realIp) return realIp.trim();

  // cf-connecting-ip is Cloudflare-specific
  const cfIp = headers.get("cf-connecting-ip");
  if (cfIp) return cfIp.trim();

  return "unknown";
}

// Handle POST request verification (Docker Application Payload verification)
export async function POST(request: Request) {
  try {
    const body = await request.json().catch(() => ({}));
    const key = body.key;

    if (!key) {
      return NextResponse.json(
        { valid: false, error: "License key parameter 'key' is required" },
        { status: 400 }
      );
    }

    return await verifyLicenseKey(key, request);
  } catch (error: any) {
    console.error("API error during POST license verification:", error);
    return NextResponse.json(
      { valid: false, error: error.message || "Internal Server Error" },
      { status: 500 }
    );
  }
}

// Handle GET request verification (Query Parameter verification)
export async function GET(request: Request) {
  try {
    const { searchParams } = new URL(request.url);
    const key = searchParams.get("key");

    if (!key) {
      return NextResponse.json(
        { valid: false, error: "License key query parameter '?key=' is required" },
        { status: 400 }
      );
    }

    return await verifyLicenseKey(key, request);
  } catch (error: any) {
    console.error("API error during GET license verification:", error);
    return NextResponse.json(
      { valid: false, error: error.message || "Internal Server Error" },
      { status: 500 }
    );
  }
}

// Helper core verification logic querying Firestore
async function verifyLicenseKey(key: string, request: Request) {
  const adminDb = getAdminDb();
  const licensesRef = adminDb.collection("licenses");
  const snapshot = await licensesRef.where("key", "==", key).limit(1).get();

  if (snapshot.empty) {
    return NextResponse.json(
      { valid: false, status: "not_found", error: "License key not registered in system database" },
      { status: 404 }
    );
  }

  const doc = snapshot.docs[0];
  const licenseData = doc.data();

  // Check expiration bounds
  const now = new Date();
  const expiresAt = new Date(licenseData.expiresAt);

  if (licenseData.status === "active" && expiresAt < now) {
    // Transition status to expired in database
    await doc.ref.update({ status: "expired" });
    licenseData.status = "expired";
  }

  // Extract caller IP for tracking (regardless of license validity)
  const clientIp = getClientIp(request);
  const verifiedAt = new Date().toISOString();

  // Update license document with caller IP and verification timestamp
  try {
    await doc.ref.update({
      lastIp: clientIp,
      lastVerifiedAt: verifiedAt,
    });
  } catch (e) {
    // Non-critical: log but don't fail verification
    console.warn("Failed to update license IP tracking:", e);
  }

  if (licenseData.status !== "active") {
    return NextResponse.json({
      valid: false,
      status: licenseData.status,
      error: `License key status is currently '${licenseData.status}'`,
      expiresAt: licenseData.expiresAt,
    });
  }

  // Fetch corporate tenant organization details
  const orgSnap = await adminDb.collection("organizations").doc(licenseData.orgId).get();
  const orgName = orgSnap.exists ? orgSnap.data()?.name : "Unknown Client";

  return NextResponse.json({
    valid: true,
    status: "active",
    expiresAt: licenseData.expiresAt,
    orgId: licenseData.orgId,
    orgName: orgName,
    productId: licenseData.productId,
    lastIp: clientIp,
    lastVerifiedAt: verifiedAt,
  });
}
