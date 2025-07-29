import React, { useState } from 'react';

interface Project {
  id: number;
  name: string;
  client: string;
  status: '준비중' | '진행중' | '검토중' | '완료' | '보류';
  startDate: string;
  endDate: string;
  budget: string;
}

const Projects: React.FC = () => {
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('all');

  const projects: Project[] = [
    {
      id: 1,
      name: '기업 웹사이트 리뉴얼',
      client: '(주)테크솔루션',
      status: '진행중',
      startDate: '2024-03-01',
      endDate: '2024-05-31',
      budget: '₩25,000,000',
    },
    {
      id: 2,
      name: '모바일 앱 개발',
      client: '스타트업 A',
      status: '검토중',
      startDate: '2024-04-01',
      endDate: '2024-07-31',
      budget: '₩45,000,000',
    },
    {
      id: 3,
      name: 'ERP 시스템 구축',
      client: '대기업 B',
      status: '완료',
      startDate: '2023-10-01',
      endDate: '2024-02-29',
      budget: '₩120,000,000',
    },
  ];

  const filteredProjects = projects.filter((project) => {
    const matchesSearch = project.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      project.client.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesStatus = statusFilter === 'all' || project.status === statusFilter;
    return matchesSearch && matchesStatus;
  });

  const getStatusColor = (status: Project['status']) => {
    switch (status) {
      case '완료':
        return 'bg-green-100 text-green-800';
      case '진행중':
        return 'bg-blue-100 text-blue-800';
      case '검토중':
        return 'bg-yellow-100 text-yellow-800';
      case '준비중':
        return 'bg-purple-100 text-purple-800';
      case '보류':
        return 'bg-red-100 text-red-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  return (
    <div>
      <div className="mb-8">
        <h1 className="text-2xl font-semibold text-gray-900">프로젝트 관리</h1>
      </div>

      {/* Filters */}
      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label htmlFor="search" className="sr-only">
            프로젝트 검색
          </label>
          <input
            type="search"
            id="search"
            className="input-field"
            placeholder="프로젝트 또는 고객사 검색..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
          />
        </div>
        <div>
          <select
            className="input-field"
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
          >
            <option value="all">모든 상태</option>
            <option value="준비중">준비중</option>
            <option value="진행중">진행중</option>
            <option value="검토중">검토중</option>
            <option value="완료">완료</option>
            <option value="보류">보류</option>
          </select>
        </div>
      </div>

      {/* Projects List */}
      <div className="bg-white shadow overflow-hidden sm:rounded-md">
        <ul className="divide-y divide-gray-200">
          {filteredProjects.map((project) => (
            <li key={project.id}>
              <div className="px-4 py-4 sm:px-6">
                <div className="flex items-center justify-between">
                  <div>
                    <h3 className="text-lg font-medium text-primary-600">
                      {project.name}
                    </h3>
                    <p className="mt-1 text-sm text-gray-500">{project.client}</p>
                  </div>
                  <div className="ml-2 flex-shrink-0">
                    <span
                      className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusColor(
                        project.status
                      )}`}
                    >
                      {project.status}
                    </span>
                  </div>
                </div>
                <div className="mt-2 sm:flex sm:justify-between">
                  <div className="sm:flex">
                    <p className="flex items-center text-sm text-gray-500">
                      {project.startDate} ~ {project.endDate}
                    </p>
                  </div>
                  <div className="mt-2 flex items-center text-sm text-gray-500 sm:mt-0">
                    <p className="font-medium">{project.budget}</p>
                  </div>
                </div>
              </div>
            </li>
          ))}
        </ul>
      </div>

      {/* Add Project Button */}
      <div className="mt-6">
        <button className="btn-primary">
          새 프로젝트 추가
        </button>
      </div>
    </div>
  );
};

export default Projects; 