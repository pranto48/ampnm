import { NextResponse } from "next/server";
import { getAdminDb } from "@/lib/firebase-admin";

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

    return await verifyLicenseKey(key);
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

    return await verifyLicenseKey(key);
  } catch (error: any) {
    console.error("API error during GET license verification:", error);
    return NextResponse.json(
      { valid: false, error: error.message || "Internal Server Error" },
      { status: 500 }
    );
  }
}

// Helper core verification logic querying Firestore
async function verifyLicenseKey(key: string) {
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
  });
}
