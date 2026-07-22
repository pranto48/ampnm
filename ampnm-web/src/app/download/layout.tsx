/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
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
