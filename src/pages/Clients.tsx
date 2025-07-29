import React, { useState } from 'react';

interface Client {
  id: number;
  name: string;
  industry: string;
  contactPerson: string;
  email: string;
  phone: string;
  projectCount: number;
  totalRevenue: string;
}

const Clients: React.FC = () => {
  const [searchTerm, setSearchTerm] = useState('');

  const clients: Client[] = [
    {
      id: 1,
      name: '(주)테크솔루션',
      industry: 'IT/소프트웨어',
      contactPerson: '김철수',
      email: 'contact@techsolution.com',
      phone: '02-1234-5678',
      projectCount: 3,
      totalRevenue: '₩85,000,000',
    },
    {
      id: 2,
      name: '스타트업 A',
      industry: '모바일/앱',
      contactPerson: '이영희',
      email: 'contact@startup-a.com',
      phone: '02-2345-6789',
      projectCount: 1,
      totalRevenue: '₩45,000,000',
    },
    {
      id: 3,
      name: '대기업 B',
      industry: '제조',
      contactPerson: '박지성',
      email: 'contact@bigcorp-b.com',
      phone: '02-3456-7890',
      projectCount: 2,
      totalRevenue: '₩150,000,000',
    },
  ];

  const filteredClients = clients.filter((client) =>
    client.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    client.contactPerson.toLowerCase().includes(searchTerm.toLowerCase()) ||
    client.industry.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div>
      <div className="mb-8">
        <h1 className="text-2xl font-semibold text-gray-900">고객사 관리</h1>
      </div>

      {/* Search */}
      <div className="mb-6">
        <div className="max-w-md">
          <label htmlFor="search" className="sr-only">
            고객사 검색
          </label>
          <input
            type="search"
            id="search"
            className="input-field"
            placeholder="고객사, 담당자, 산업군으로 검색..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
          />
        </div>
      </div>

      {/* Clients List */}
      <div className="bg-white shadow overflow-hidden sm:rounded-lg">
        <table className="min-w-full divide-y divide-gray-200">
          <thead className="bg-gray-50">
            <tr>
              <th
                scope="col"
                className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
              >
                고객사
              </th>
              <th
                scope="col"
                className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
              >
                담당자
              </th>
              <th
                scope="col"
                className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
              >
                연락처
              </th>
              <th
                scope="col"
                className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
              >
                프로젝트
              </th>
              <th
                scope="col"
                className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
              >
                총 매출
              </th>
            </tr>
          </thead>
          <tbody className="bg-white divide-y divide-gray-200">
            {filteredClients.map((client) => (
              <tr key={client.id}>
                <td className="px-6 py-4 whitespace-nowrap">
                  <div>
                    <div className="text-sm font-medium text-gray-900">
                      {client.name}
                    </div>
                    <div className="text-sm text-gray-500">{client.industry}</div>
                  </div>
                </td>
                <td className="px-6 py-4 whitespace-nowrap">
                  <div className="text-sm text-gray-900">
                    {client.contactPerson}
                  </div>
                </td>
                <td className="px-6 py-4 whitespace-nowrap">
                  <div className="text-sm text-gray-900">{client.phone}</div>
                  <div className="text-sm text-gray-500">{client.email}</div>
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {client.projectCount}개
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {client.totalRevenue}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Add Client Button */}
      <div className="mt-6">
        <button className="btn-primary">
          새 고객사 추가
        </button>
      </div>
    </div>
  );
};

export default Clients; 