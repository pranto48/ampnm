import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Custom Telemetry Services & Consulting",
  description: "Request custom Go agent binaries development, network topology configuration support, and API gateway setups from IT Support BD.",
  alternates: {
    canonical: "/services",
  }
};

export default function ServicesLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
