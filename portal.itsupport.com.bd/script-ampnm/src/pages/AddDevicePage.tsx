import { useNavigate } from 'react-router-dom';
import { DeviceForm } from '@/components/DeviceForm';
import { addDevice, NetworkDevice } from '@/services/networkDeviceService';
import { showSuccess, showError } from '@/utils/toast';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Link } from 'react-router-dom';

const AddDevicePage = () => {
  const navigate = useNavigate();

  const handleSubmit = async (deviceData: Omit<NetworkDevice, 'id' | 'position_x' | 'position_y' | 'user_id'>) => {
    try {
      // Default position for new devices, can be adjusted on map later
      await addDevice({ ...deviceData, position_x: 100, position_y: 100, status: 'unknown' });
      showSuccess('Device added successfully!');
      navigate('/'); // Navigate back to the dashboard or map
    } catch (error) {
      console.error('Failed to add device:', error);
      showError('Failed to add device.');
    }
  };

  return (
    <div className="container mx-auto p-4">
      <div className="flex items-center gap-4 mb-6">
        <Button variant="ghost" size="icon" asChild>
          <Link to="/">
            <ArrowLeft className="h-5 w-5" />
          </Link>
        </Button>
        <h1 className="text-3xl font-bold">Add New Device</h1>
      </div>
      <DeviceForm onSubmit={handleSubmit} />
    </div>
  );
};

export default AddDevicePage;