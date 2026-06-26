import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Download Telemetry Agents & Scripts",
  description: "Retrieve Linux systemd curl setups, docker-compose configuration files, and Windows Server agent zip executables.",
  alternates: {
    canonical: "/download",
  }
};

export default function DownloadLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
