import React, { useEffect, useState, useCallback } from 'react';
import { LicenseInfoCard } from '@/components/LicenseInfoCard';
import { LicenseChangeForm } from '@/components/LicenseChangeForm';
import { getCurrentLicenseInfo, LicenseInfo } from '@/services/licenseService';
import { showError } from '@/utils/toast';
import { Skeleton } from '@/components/ui/skeleton';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Link } from 'react-router-dom';

const LicenseManagementPage = () => {
  const [licenseInfo, setLicenseInfo] = useState<LicenseInfo | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const fetchLicenseInfo = useCallback(async () => {
    setIsLoading(true);
    try {
      const info = await getCurrentLicenseInfo();
      setLicenseInfo(info);
    } catch (error: any) {
      console.error('Failed to fetch license info:', error);
      showError(error.message || 'Failed to load license information.');
      setLicenseInfo(null); // Clear info on error
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchLicenseInfo();
  }, [fetchLicenseInfo]);

  return (
    <div className="container mx-auto p-4">
      <div className="flex items-center gap-4 mb-6">
        <Button variant="ghost" size="icon" asChild>
          <Link to="/">
            <ArrowLeft className="h-5 w-5" />
          </Link>
        </Button>
        <h1 className="text-3xl font-bold">License Management</h1>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <LicenseInfoCard licenseInfo={licenseInfo} isLoading={isLoading} />
        {isLoading ? (
          <div className="w-full">
            <Skeleton className="h-24 w-full mb-4" />
            <Skeleton className="h-12 w-full" />
          </div>
        ) : (
          licenseInfo && <LicenseChangeForm currentLicenseKey={licenseInfo.app_license_key} onLicenseUpdated={fetchLicenseInfo} />
        )}
      </div>
    </div>
  );
};

export default LicenseManagementPage;