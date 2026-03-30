import React, { useState } from 'react';
import { useHistory } from 'react-router-dom';

const NetworkMapPage: React.FC = () => {
  const history = useHistory();
  const [selectedMap, setSelectedMap] = useState<string>('default');

  const handleNewMap = () => {
    console.log('Creating new map...');
  };

  return (
    <div className="flex flex-col h-screen bg-gray-50">
      <header className="bg-white shadow-sm p-4">
        <div className="flex justify-between items-center">
          <h1 className="text-2xl font-bold">Network Map</h1>
          <button
            onClick={() => history.push('/')}
            className="text-blue-600 hover:underline"
          >
            Logout
          </button>
        </div>
      </header>
      <div className="p-4">
        <div className="flex gap-4 mb-4">
          <button
            onClick={handleNewMap}
            className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700"
          >
            New Map
          </button>
          <select
            value={selectedMap}
            onChange={(e) => setSelectedMap(e.target.value)}
            className="border rounded-md px-3 py-2"
          >
            <option value="default">Default Map</option>
            <option value="datacenter">Data Center</option>
            <option value="office">Office Network</option>
          </select>
        </div>
        <div className="bg-white rounded-lg shadow p-4 h-96">
          <p className="text-gray-500">Map visualization area</p>
        </div>
      </div>
    </div>
  );
};

export default NetworkMapPage;