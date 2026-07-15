import { create } from "zustand";
import { Organization, Product, License, UserProfile, PaymentSettings, MailSettings, EmailLog } from "@/types";
import { app } from "@/lib/firebase";
import { getFirestore, collection, getDocs, doc, setDoc, getDoc, updateDoc } from "firebase/firestore";

interface MonitorState {
  sidebarOpen: boolean;
  setSidebarOpen: (open: boolean) => void;
  toggleSidebar: () => void;

  organizations: Organization[];
  products: Product[];
  licenses: License[];
  profile: UserProfile | null;
  paymentSettings: PaymentSettings;
  mailSettings: MailSettings;
  emailLogs: EmailLog[];

  setProfile: (profile: UserProfile | null) => void;
  addOrganization: (org: Organization) => Promise<void> | void;
  addLicense: (license: License) => Promise<void> | void;
  revokeLicense: (id: string) => Promise<void> | void;
  updatePaymentSettings: (settings: PaymentSettings) => void;
  updateMailSettings: (settings: MailSettings) => void;
  addEmailLog: (log: EmailLog) => void;
  toggleOrgVerification: (orgId: string, verified: boolean) => Promise<void> | void;
  syncWithFirestore: () => Promise<void>;
}

export const useMonitorStore = create<MonitorState>((set) => ({
  sidebarOpen: true,
  setSidebarOpen: (open) => set({ sidebarOpen: open }),
  toggleSidebar: () => set((state) => ({ sidebarOpen: !state.sidebarOpen })),

  // Seeded client accounts list (Client Management)
  organizations: [
    { id: "org-bb", name: "Bangladesh Bank IT", createdAt: "2026-01-10", clientEmail: "it-admin@bb.org.bd", licenseCount: 15, verified: true },
    { id: "org-gp", name: "Grameenphone Infrastructure", createdAt: "2026-02-15", clientEmail: "ops@grameenphone.com", licenseCount: 42, verified: true },
    { id: "org-it", name: "IT Support BD Operations", createdAt: "2026-01-01", clientEmail: "arif@itsupport.com.bd", licenseCount: 5, verified: true },
    { id: "org-dfn", name: "Dhaka Fiber Net Node", createdAt: "2025-05-12", clientEmail: "support@dhakafibernet.com", licenseCount: 0, verified: false },
  ],

  // Seeded product pricing packages (Product Management)
  products: [
    {
      id: "prod-ampnm-free",
      name: "AMPNM Core (Free & Open Source)",
      price: 0,
      billingPeriod: "lifetime",
      features: ["Free & Open Source License", "Unlimited server host allocations", "Self-hosted Docker dashboard", "Host-locked security key verification"],
    },
    {
      id: "prod-std",
      name: "Standard Agent License",
      price: 15,
      billingPeriod: "monthly",
      features: ["Single host CPU/RAM tracking", "Email incident warnings", "24/7 client portal support", "Up to 5 custom dashboard widgets"],
    },
    {
      id: "prod-cluster",
      name: "Docker Cluster Pack",
      price: 99,
      billingPeriod: "monthly",
      features: ["Up to 10 cluster nodes monitoring", "SMS + Telegram alert integrations", "14-day history metrics graphs", "Dedicated support line access"],
    },
    {
      id: "prod-enterprise",
      name: "Enterprise Core Unlimited",
      price: 499,
      billingPeriod: "monthly",
      features: ["Unlimited server host allocations", "Custom Webhook endpoints reporting", "Full REST API access", "99.9% SLA support contract"],
    },
    {
      id: "prod-ampos",
      name: "AmPOS Pro License",
      price: 49,
      billingPeriod: "one-time",
      features: [
        "Single-terminal Point of Sale activation",
        "Lifetime license verification & support",
        "Full sales, stock, and contact database modules",
        "Encrypted portal protection keys shield"
      ],
    },
  ],

  // Seeded issued license key details (Licensing Management)
  licenses: [
    { id: "l1", key: "AMPNM-DEVC-8F2B-9A4E-4321", orgId: "org-bb", productId: "prod-cluster", status: "active", createdAt: "2026-01-10", expiresAt: "2027-01-10" },
    { id: "l2", key: "AMPNM-DEVC-3C5D-8E1A-7654", orgId: "org-gp", productId: "prod-enterprise", status: "active", createdAt: "2026-02-15", expiresAt: "2027-02-15" },
    { id: "l3", key: "AMPNM-DEVC-5D4E-1C2A-9876", orgId: "org-dfn", productId: "prod-std", status: "expired", createdAt: "2025-05-12", expiresAt: "2026-05-12" },
    { id: "l4", key: "AMPNM-DEVC-1B2C-3D4E-5F6A", orgId: "org-it", productId: "prod-cluster", status: "active", createdAt: "2026-01-01", expiresAt: "2027-01-01" },
  ],

  profile: {
    uid: "u1",
    name: "Sayed Arif",
    email: "arif@itsupport.com.bd",
    role: "owner",
    orgId: "org-it",
    createdAt: "2026-01-01",
  },

  // Default Admin Settings (Payments)
  paymentSettings: {
    bkash: { enabled: true, number: "01915822266", type: "personal" },
    rocket: { enabled: true, number: "019158222660", type: "personal" },
    nagad: { enabled: true, number: "01915822266", type: "personal" },
  },

  // Default Email Settings (Resend configuration)
  mailSettings: {
    resendApiKey: "",
    fromEmail: "licensing@itsupport.com.bd",
  },

  // Outgoing communications log
  emailLogs: [
    {
      id: "log-1",
      recipient: "ops@grameenphone.com",
      subject: "Welcome to AMPNM Licensing Portal",
      body: "Welcome! Your organization has been verified and registered by IT Support BD. Your profile role is 'owner'.",
      timestamp: "2026-06-25T10:00:00Z",
      status: "simulated",
      type: "verification"
    }
  ],

  setProfile: (profile) => set({ profile }),
  addOrganization: async (org) => {
    try {
      const db = getFirestore(app);
      await setDoc(doc(db, "organizations", org.id), org);
      set((state) => ({ organizations: [org, ...state.organizations] }));
    } catch (e) {
      console.error("Failed to add organization in Firestore:", e);
    }
  },
  addLicense: async (license) => {
    try {
      const db = getFirestore(app);
      await setDoc(doc(db, "licenses", license.id), {
        key: license.key,
        orgId: license.orgId,
        productId: license.productId,
        status: license.status,
        createdAt: license.createdAt,
        expiresAt: license.expiresAt,
      });

      // Update org count in firestore
      const orgRef = doc(db, "organizations", license.orgId);
      const orgSnap = await getDoc(orgRef);
      if (orgSnap.exists()) {
        const count = orgSnap.data().licenseCount || 0;
        await updateDoc(orgRef, { licenseCount: count + 1 });
      }

      set((state) => {
        const updatedOrgs = state.organizations.map((org) =>
          org.id === license.orgId ? { ...org, licenseCount: org.licenseCount + 1 } : org
        );
        return {
          licenses: [license, ...state.licenses],
          organizations: updatedOrgs,
        };
      });
    } catch (e) {
      console.error("Failed to add license in Firestore:", e);
    }
  },
  revokeLicense: async (id) => {
    try {
      const db = getFirestore(app);
      const licRef = doc(db, "licenses", id);
      await updateDoc(licRef, { status: "revoked" });

      const licSnap = await getDoc(licRef);
      if (licSnap.exists()) {
        const licenseData = licSnap.data();
        const orgId = licenseData.orgId;
        const orgRef = doc(db, "organizations", orgId);
        const orgSnap = await getDoc(orgRef);
        if (orgSnap.exists()) {
          const count = orgSnap.data().licenseCount || 0;
          await updateDoc(orgRef, { licenseCount: Math.max(0, count - 1) });
        }
      }

      set((state) => {
        const targetLic = state.licenses.find((l) => l.id === id);
        if (!targetLic) return {};
        const updatedOrgs = state.organizations.map((org) =>
          org.id === targetLic.orgId ? { ...org, licenseCount: Math.max(0, org.licenseCount - 1) } : org
        );
        return {
          licenses: state.licenses.map((lic) =>
            lic.id === id ? { ...lic, status: "revoked" as const } : lic
          ),
          organizations: updatedOrgs,
        };
      });
    } catch (e) {
      console.error("Failed to revoke license in Firestore:", e);
    }
  },
  updatePaymentSettings: (settings) => set({ paymentSettings: settings }),
  updateMailSettings: (settings) => set({ mailSettings: settings }),
  addEmailLog: (log) => set((state) => ({ emailLogs: [log, ...state.emailLogs] })),
  toggleOrgVerification: async (orgId, verified) => {
    try {
      const db = getFirestore(app);
      await updateDoc(doc(db, "organizations", orgId), { verified });
      set((state) => ({
        organizations: state.organizations.map((org) =>
          org.id === orgId ? { ...org, verified } : org
        )
      }));
    } catch (e) {
      console.error("Failed to update org verification in Firestore:", e);
    }
  },
  syncWithFirestore: async () => {
    try {
      const db = getFirestore(app);
      const orgsSnap = await getDocs(collection(db, "organizations"));
      const orgsList: Organization[] = [];
      orgsSnap.forEach((doc) => {
        orgsList.push({ id: doc.id, ...doc.data() } as Organization);
      });

      const licsSnap = await getDocs(collection(db, "licenses"));
      const licsList: License[] = [];
      licsSnap.forEach((doc) => {
        licsList.push({ id: doc.id, ...doc.data() } as License);
      });

      set({
        organizations: orgsList,
        licenses: licsList,
      });
    } catch (e) {
      console.error("Failed to sync store with Firestore:", e);
    }
  },
}));

