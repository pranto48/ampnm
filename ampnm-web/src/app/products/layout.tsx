import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Agent Telemetry Specifications & Products",
  description: "Discover background telemetry trackers, load indicators warning protocols, and REST key integration configurations.",
  alternates: {
    canonical: "/products",
  }
};

export default function ProductsLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
