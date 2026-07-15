export interface Organization {
  id: string;
  name: string;
  createdAt: string | Date;
  clientEmail: string;
  licenseCount: number;
  verified?: boolean;
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
  billingPeriod: "monthly" | "yearly" | "one-time" | "lifetime";
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
  lastIp?: string;
  lastVerifiedAt?: string;
}

export interface PaymentOption {
  enabled: boolean;
  number: string;
  type: "personal" | "merchant";
}

export interface PaymentSettings {
  bkash: PaymentOption;
  rocket: PaymentOption;
  nagad: PaymentOption;
}

export interface MailSettings {
  resendApiKey: string;
  fromEmail: string;
}

export interface EmailLog {
  id: string;
  recipient: string;
  subject: string;
  body: string;
  timestamp: string;
  status: "sent" | "failed" | "simulated";
  type: "verification" | "license" | "custom";
}

