import { getDockerBaseUrl } from '../utils/url';

const LOCAL_API_URL = `${getDockerBaseUrl()}/api.php`;

interface ApiResponse<T> {
  success?: boolean;
  error?: string;
  message?: string;
  data?: T;
}

export interface LicenseInfo {
  license_status_code: string;
  license_message: string;
  app_license_key: string;
  installation_id: string;
  max_devices: number;
  current_devices: number;
  expires_at: string | null;
  grace_period_end: number | null; // Unix timestamp
}

const callApi = async <T>(action: string, method: 'GET' | 'POST', body?: any): Promise<T> => {
  const options: RequestInit = {
    method: method,
    headers: {
      'Content-Type': 'application/json',
    },
  };
  if (body) {
    options.body = JSON.stringify(body);
  }

  const response = await fetch(`${LOCAL_API_URL}?action=${action}`, options);
  const result: ApiResponse<T> = await response.json();

  if (!response.ok || result.error) {
    throw new Error(result.error || `API call failed with status ${response.status}`);
  }
  return result as T;
};

export const getCurrentLicenseInfo = async (): Promise<LicenseInfo> => {
  const result = await callApi<LicenseInfo>('get_current_license_info', 'GET');
  return result;
};

export const updateAppLicenseKey = async (licenseKey: string): Promise<{ success: boolean; message: string }> => {
  const result = await callApi<{ success: boolean; message: string }>('update_app_license_key', 'POST', { license_key: licenseKey });
  return result;
};

export const forceLicenseRecheck = async (): Promise<{ success: boolean; message: string }> => {
  const result = await callApi<{ success: boolean; message: string }>('force_license_recheck', 'POST');
  return result;
};