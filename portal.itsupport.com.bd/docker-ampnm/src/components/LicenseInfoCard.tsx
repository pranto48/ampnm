import React from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { CheckCircle, XCircle, AlertTriangle, Info, Server, Calendar, Clock } from 'lucide-react';
import { LicenseInfo } from '@/services/licenseService';

interface LicenseInfoCardProps {
  licenseInfo: LicenseInfo;
  isLoading: boolean;
}

const getStatusBadge = (statusCode: string) => {
  switch (statusCode) {
    case 'active':
    case 'free':
      return <Badge variant="default" className="bg-green-500 hover:bg-green-500"><CheckCircle className="h-4 w-4 mr-1" /> Active</Badge>;
    case 'grace_period':
      return <Badge variant="secondary" className="bg-yellow-500 hover:bg-yellow-500"><AlertTriangle className="h-4 w-4 mr-1" /> Grace Period</Badge>;
    case 'unconfigured':
      return <Badge variant="secondary" className="bg-blue-500 hover:bg-blue-500"><Info className="h-4 w-4 mr-1" /> Unconfigured</Badge>;
    case 'portal_unreachable':
      return <Badge variant="destructive" className="bg-orange-500 hover:bg-orange-500"><AlertTriangle className="h-4 w-4 mr-1" /> Portal Unreachable</Badge>;
    case 'expired':
    case 'revoked':
    case 'in_use':
    case 'disabled':
    case 'invalid':
    case 'not_found':
    case 'error':
    default:
      return <Badge variant="destructive" className="bg-red-500 hover:bg-red-500"><XCircle className="h-4 w-4 mr-1" /> Disabled</Badge>;
  }
};

export const LicenseInfoCard = ({ licenseInfo, isLoading }: LicenseInfoCardProps) => {
  if (isLoading) {
    return (
      <Card className="w-full">
        <CardHeader>
          <div className="flex items-center justify-between">
            <div className="h-6 bg-gray-200 rounded w-48 animate-pulse"></div>
            <div className="h-6 bg-gray-200 rounded w-24 animate-pulse"></div>
          </div>
          <div className="h-4 bg-gray-200 rounded w-3/4 mt-2 animate-pulse"></div>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="h-4 bg-gray-200 rounded w-full animate-pulse"></div>
          <div className="h-4 bg-gray-200 rounded w-full animate-pulse"></div>
          <div className="h-4 bg-gray-200 rounded w-full animate-pulse"></div>
          <div className="h-4 bg-gray-200 rounded w-full animate-pulse"></div>
        </CardContent>
      </Card>
    );
  }

  const {
    license_status_code,
    license_message,
    app_license_key,
    installation_id,
    max_devices,
    current_devices,
    expires_at,
    grace_period_end,
  } = licenseInfo;

  const expiryDate = expires_at ? new Date(expires_at).toLocaleDateString() : 'N/A';
  const gracePeriodEndDate = grace_period_end ? new Date(grace_period_end * 1000).toLocaleDateString() : 'N/A';

  return (
    <Card className="w-full">
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle>Current License Status</CardTitle>
          {getStatusBadge(license_status_code)}
        </div>
        <CardDescription>{license_message}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4 text-sm">
        <div className="flex items-center justify-between">
          <span className="flex items-center text-muted-foreground"><Info className="h-4 w-4 mr-2" />License Key:</span>
          <span className="font-mono text-white break-all">{app_license_key || 'Not Set'}</span>
        </div>
        <div className="flex items-center justify-between">
          <span className="flex items-center text-muted-foreground"><Server className="h-4 w-4 mr-2" />Installation ID:</span>
          <span className="font-mono text-white break-all">{installation_id || 'Not Set'}</span>
        </div>
        <div className="flex items-center justify-between">
          <span className="flex items-center text-muted-foreground"><Server className="h-4 w-4 mr-2" />Device Usage:</span>
          <span className="text-white">{current_devices} / {max_devices === 0 ? 'Unlimited' : max_devices}</span>
        </div>
        <div className="flex items-center justify-between">
          <span className="flex items-center text-muted-foreground"><Calendar className="h-4 w-4 mr-2" />Expires On:</span>
          <span className="text-white">{expiryDate}</span>
        </div>
        {license_status_code === 'grace_period' && (
          <div className="flex items-center justify-between text-yellow-500">
            <span className="flex items-center"><Clock className="h-4 w-4 mr-2" />Grace Period Ends:</span>
            <span>{gracePeriodEndDate}</span>
          </div>
        )}
      </CardContent>
    </Card>
  );
};