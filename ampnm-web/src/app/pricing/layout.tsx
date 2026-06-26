import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "SaaS Licensing Pricing Packages",
  description: "Detailed features comparison matrix of Standard, Docker Cluster Pack, and Enterprise Core unlimited licensing plans.",
  alternates: {
    canonical: "/pricing",
  }
};

export default function PricingLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
