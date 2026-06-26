const { initializeApp, cert } = require("firebase-admin/app");
const { getAuth } = require("firebase-admin/auth");
const { getFirestore } = require("firebase-admin/firestore");
require("dotenv").config({ path: ".env.local" });

// Load credentials
const projectId = process.env.FIREBASE_PROJECT_ID;
const clientEmail = process.env.FIREBASE_CLIENT_EMAIL;
const privateKey = process.env.FIREBASE_PRIVATE_KEY;

if (!projectId || !clientEmail || !privateKey || privateKey.includes("MockPrivateKey")) {
  console.error("Error: Please make sure a valid FIREBASE_PRIVATE_KEY is defined in .env.local before running this script.");
  process.exit(1);
}

const app = initializeApp({
  credential: cert({
    projectId,
    clientEmail,
    privateKey: privateKey.replace(/\\n/g, "\n"),
  }),
});

const auth = getAuth(app);
const db = getFirestore(app);

const targetEmail = "mail@arifmahmud.com";

async function run() {
  try {
    console.log(`Searching for user with email: ${targetEmail}...`);
    const userRecord = await auth.getUserByEmail(targetEmail);
    console.log(`Found Firebase user: ${userRecord.uid}`);

    const userRef = db.collection("users").doc(userRecord.uid);
    
    console.log("Updating Firestore user profile document...");
    await userRef.set({
      uid: userRecord.uid,
      name: userRecord.displayName || "Admin",
      email: targetEmail,
      role: "admin",
      orgId: "org-it", // Associated with IT Support BD operations
      updatedAt: new Date().toISOString()
    }, { merge: true });

    console.log(`Successfully made ${targetEmail} a portal Administrator!`);
  } catch (error) {
    console.error("Failed to run admin assignment script:", error);
  }
}

run();
