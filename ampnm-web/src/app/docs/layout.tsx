import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Integration Documentation & API References",
  description: "Reference guides for verifying software license keys against REST API endpoint URLs and whitelisting outbound firewall rules.",
  alternates: {
    canonical: "/docs",
  }
};

export default function DocsLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
