export interface Organization {
  id: string;
  name: string;
  createdAt: string | Date;
  clientEmail: string;
  licenseCount: number;
}

export interface UserProfile {
  uid: string;
  name: string;
  email: string;
  role: "owner" | "admin" | "member";
  orgId: string;
  createdAt: string | Date;
}

export interface Product {
  id: string;
  name: string;
  price: number;
  billingPeriod: "monthly" | "yearly" | "one-time";
  features: string[];
}

export interface License {
  id: string;
  key: string;
  orgId: string;
  productId: string;
  status: "active" | "revoked" | "expired";
  createdAt: string | Date;
  expiresAt: string | Date;
}
