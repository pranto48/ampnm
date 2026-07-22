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
  title: "Integration Documentation & API References",
  description: "Reference guides for verifying software license keys against REST API endpoint URLs and whitelisting outbound firewall rules.",
  alternates: {
    canonical: "/docs",
  }
};

export default function DocsLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
