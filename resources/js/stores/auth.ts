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
    tenantName: localStorage.getItem('tenant_name'),
  }),
  actions: {
    setToken(token: string) {
      localStorage.setItem('token', token);
    },
    setTenant(id: string, name: string | null = null) {
      this.tenantId = id;
      this.tenantName = name;
      localStorage.setItem('tenant_id', id);
      if (name !== null) {
        localStorage.setItem('tenant_name', name);
      } else {
        localStorage.removeItem('tenant_name');
      }
    },
    clearTenant() {
      this.tenantId = null;
      this.tenantName = null;
      localStorage.removeItem('tenant_id');
      localStorage.removeItem('tenant_name');
    },
    logout() {
      localStorage.removeItem('token');
      localStorage.removeItem('tenant_id');
      localStorage.removeItem('tenant_name');
      this.user = null;
      this.tenantId = null;
      this.tenantName = null;
    },
  },
});
