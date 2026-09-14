import axios from 'axios';

export const API_BASE = 'http://localhost:8000/api';

// 普通请求实例
export const api = axios.create({ baseURL: API_BASE });

// 带管理员令牌的请求实例
export const adminApi = axios.create({ baseURL: API_BASE });

const ADMIN_TOKEN_KEY = 'cinevault_admin_token';

export const getAdminToken = () => localStorage.getItem(ADMIN_TOKEN_KEY);
export const setAdminToken = (token) => localStorage.setItem(ADMIN_TOKEN_KEY, token);
export const clearAdminToken = () => localStorage.removeItem(ADMIN_TOKEN_KEY);

adminApi.interceptors.request.use((config) => {
  const token = getAdminToken();
  if (token) {
    config.headers['X-Admin-Token'] = token;
  }
  return config;
});

adminApi.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      clearAdminToken();
      // 通知界面跳转登录（后台页会监听该事件）
      window.dispatchEvent(new CustomEvent('admin-unauthorized'));
    }
    return Promise.reject(error);
  }
);
