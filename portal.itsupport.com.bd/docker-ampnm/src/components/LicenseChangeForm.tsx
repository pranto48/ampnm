import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Key, RefreshCw } from 'lucide-react';
import { showSuccess, showError, showLoading, dismissToast } from '@/utils/toast';
import { updateAppLicenseKey, forceLicenseRecheck } from '@/services/licenseService';

interface LicenseChangeFormProps {
  currentLicenseKey: string;
  onLicenseUpdated: () => void;
}

export const LicenseChangeForm = ({ currentLicenseKey, onLicenseUpdated }: LicenseChangeFormProps) => {
  const [newLicenseKey, setNewLicenseKey] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isRechecking, setIsRechecking] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newLicenseKey.trim()) {
      showError('Please enter a new license key.');
      return;
    }

    setIsSubmitting(true);
    const toastId = showLoading('Updating license key...');
    try {
      const result = await updateAppLicenseKey(newLicenseKey);
      dismissToast(toastId);
      if (result.success) {
        showSuccess(result.message);
        setNewLicenseKey('');
        onLicenseUpdated(); // Trigger parent to refresh license info
      } else {
        showError(result.message || 'Failed to update license key.');
      }
    } catch (error: any) {
      dismissToast(toastId);
      console.error('Error updating license key:', error);
      showError(error.message || 'An unexpected error occurred.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleRecheck = async () => {
    setIsRechecking(true);
    const toastId = showLoading('Re-verifying license with portal...');
    try {
      const result = await forceLicenseRecheck();
      dismissToast(toastId);
      if (result.success) {
        showSuccess(result.message);
        onLicenseUpdated(); // Trigger parent to refresh license info
      } else {
        showError(result.message || 'Failed to re-verify license.');
      }
    } catch (error: any) {
      dismissToast(toastId);
      console.error('Error re-verifying license:', error);
      showError(error.message || 'An unexpected error occurred during re-verification.');
    } finally {
      setIsRechecking(false);
    }
  };

  return (
    <Card className="w-full">
      <CardHeader>
        <CardTitle>Change License Key</CardTitle>
        <CardDescription>Enter a new license key to update your application's license.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label htmlFor="newLicenseKey" className="sr-only">New License Key</label>
            <Input
              id="newLicenseKey"
              type="text"
              placeholder="Enter new license key (e.g., XXXX-XXXX-XXXX-XXXX)"
              value={newLicenseKey}
              onChange={(e) => setNewLicenseKey(e.target.value)}
              disabled={isSubmitting}
            />
          </div>
          <Button type="submit" className="w-full" disabled={isSubmitting}>
            <Key className="h-4 w-4 mr-2" />
            {isSubmitting ? 'Updating...' : 'Update License Key'}
          </Button>
        </form>
        <div className="relative flex items-center">
          <span className="flex-grow border-t border-gray-700"></span>
          <span className="flex-shrink mx-4 text-gray-500 text-sm">OR</span>
          <span className="flex-grow border-t border-gray-700"></span>
        </div>
        <Button onClick={handleRecheck} className="w-full" variant="outline" disabled={isRechecking}>
          <RefreshCw className={`h-4 w-4 mr-2 ${isRechecking ? 'animate-spin' : ''}`} />
          {isRechecking ? 'Re-verifying...' : 'Re-verify Current License'}
        </Button>
      </CardContent>
    </Card>
  );
};