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
  title: "Custom Telemetry Services & Consulting",
  description: "Request custom Go agent binaries development, network topology configuration support, and API gateway setups from IT Support BD.",
  alternates: {
    canonical: "/services",
  }
};

export default function ServicesLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
