import React from 'react';

const Dashboard: React.FC = () => {
  const stats = [
    { name: '활성 프로젝트', value: '12' },
    { name: '완료된 프로젝트', value: '89' },
    { name: '전체 고객', value: '45' },
    { name: '이번 달 수익', value: '₩15,300,000' },
  ];

  const recentActivities = [
    {
      id: 1,
      project: '웹사이트 리뉴얼',
      client: '(주)테크솔루션',
      status: '진행중',
      date: '2024-03-15',
    },
    {
      id: 2,
      project: '모바일 앱 개발',
      client: '스타트업 A',
      status: '검토중',
      date: '2024-03-14',
    },
    {
      id: 3,
      project: 'ERP 시스템 구축',
      client: '대기업 B',
      status: '완료',
      date: '2024-03-13',
    },
  ];

  return (
    <div>
      <div className="mb-8">
        <h1 className="text-2xl font-semibold text-gray-900">대시보드</h1>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        {stats.map((item) => (
          <div
            key={item.name}
            className="bg-white overflow-hidden shadow rounded-lg"
          >
            <div className="px-4 py-5 sm:p-6">
              <dt className="text-sm font-medium text-gray-500 truncate">
                {item.name}
              </dt>
              <dd className="mt-1 text-3xl font-semibold text-gray-900">
                {item.value}
              </dd>
            </div>
          </div>
        ))}
      </div>

      {/* Recent Activities */}
      <div className="mt-8">
        <h2 className="text-lg font-medium text-gray-900 mb-4">최근 활동</h2>
        <div className="bg-white shadow overflow-hidden sm:rounded-md">
          <ul className="divide-y divide-gray-200">
            {recentActivities.map((activity) => (
              <li key={activity.id}>
                <div className="px-4 py-4 sm:px-6">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center">
                      <p className="text-sm font-medium text-primary-600 truncate">
                        {activity.project}
                      </p>
                      <p className="ml-2 text-sm text-gray-500">
                        {activity.client}
                      </p>
                    </div>
                    <div className="ml-2 flex-shrink-0 flex">
                      <p
                        className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                          activity.status === '완료'
                            ? 'bg-green-100 text-green-800'
                            : activity.status === '진행중'
                            ? 'bg-blue-100 text-blue-800'
                            : 'bg-yellow-100 text-yellow-800'
                        }`}
                      >
                        {activity.status}
                      </p>
                    </div>
                  </div>
                  <div className="mt-2 sm:flex sm:justify-between">
                    <div className="sm:flex">
                      <p className="flex items-center text-sm text-gray-500">
                        {activity.date}
                      </p>
                    </div>
                  </div>
                </div>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </div>
  );
};

export default Dashboard; 