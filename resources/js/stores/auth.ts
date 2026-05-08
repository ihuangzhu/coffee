import { defineStore } from 'pinia';

interface User {
  id: string;
  phone: string;
  name: string;
  is_platform_admin: boolean;
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as User | null,
    tenantId: localStorage.getItem('tenant_id'),
  }),
  actions: {
    setToken(token: string) {
      localStorage.setItem('token', token);
    },
    setTenant(id: string) {
      this.tenantId = id;
      localStorage.setItem('tenant_id', id);
    },
    clearTenant() {
      this.tenantId = null;
      localStorage.removeItem('tenant_id');
    },
    logout() {
      localStorage.removeItem('token');
      localStorage.removeItem('tenant_id');
      this.user = null;
      this.tenantId = null;
    },
  },
});
