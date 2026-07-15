import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  async rewrites() {
    return [
      {
        source: "/verify_license.php",
        destination: "/api/license/verify",
      },
    ];
  },
};

export default nextConfig;
