import create from 'zustand';
import { AuthState, LoginCredentials, User } from '../types/auth';

// 테스트용 관리자 계정
const ADMIN_CREDENTIALS = {
  email: 'admin@howcontent.com',
  password: 'admin1234!',
};

const ADMIN_USER: User = {
  id: '1',
  username: '관리자',
  email: ADMIN_CREDENTIALS.email,
  role: 'admin',
};

const useAuthStore = create<AuthState & {
  login: (credentials: LoginCredentials) => Promise<void>;
  logout: () => void;
}>((set) => ({
  user: null,
  isAuthenticated: false,
  isLoading: false,
  error: null,

  login: async (credentials) => {
    set({ isLoading: true, error: null });
    try {
      // 실제 환경에서는 API 호출로 대체
      await new Promise(resolve => setTimeout(resolve, 1000)); // 로딩 시뮬레이션

      if (
        credentials.email === ADMIN_CREDENTIALS.email &&
        credentials.password === ADMIN_CREDENTIALS.password
      ) {
        set({
          user: ADMIN_USER,
          isAuthenticated: true,
          isLoading: false,
        });
        // 토큰을 로컬 스토리지에 저장
        localStorage.setItem('auth_token', 'dummy_token');
      } else {
        throw new Error('이메일 또는 비밀번호가 올바르지 않습니다.');
      }
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : '로그인 중 오류가 발생했습니다.',
        isLoading: false,
      });
    }
  },

  logout: () => {
    localStorage.removeItem('auth_token');
    set({
      user: null,
      isAuthenticated: false,
      error: null,
    });
  },
}));

export default useAuthStore; 