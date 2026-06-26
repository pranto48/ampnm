import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Docker Cluster & Enterprise Solutions",
  description: "Secure compliance architectures whitelists setups, and multi-tenant telemetry dashboards for corporate server systems.",
  alternates: {
    canonical: "/solutions",
  }
};

export default function SolutionsLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
