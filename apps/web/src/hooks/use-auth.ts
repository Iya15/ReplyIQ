import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';
import { authApi, ApiError } from '@/lib/api';
import { useAuthStore } from '@/lib/auth/auth-store';
import type { LoginPayload, RegisterPayload } from '@replyiq/api-client';

export function useLogin() {
  const router = useRouter();
  const login = useAuthStore((s) => s.login);

  return useMutation({
    mutationFn: (data: LoginPayload) => authApi.login(data),
    onSuccess: (res) => {
      login(res.data.token, res.data.user);
      router.push('/dashboard');
    },
  });
}

export function useRegister() {
  const router = useRouter();
  const login = useAuthStore((s) => s.login);

  return useMutation({
    mutationFn: (data: RegisterPayload) => authApi.register(data),
    onSuccess: (res) => {
      login(res.data.token, res.data.user);
      router.push('/dashboard');
    },
  });
}

export function useLogout() {
  const router = useRouter();
  const logout = useAuthStore((s) => s.logout);
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => authApi.logout(),
    onSettled: () => {
      // Clear state regardless of API success — expired tokens still need logout.
      logout();
      queryClient.clear();
      router.push('/login');
    },
  });
}

export function useMe() {
  const token = useAuthStore((s) => s.token);
  const setUser = useAuthStore((s) => s.setUser);

  return useQuery({
    queryKey: ['me'],
    queryFn: async () => {
      const res = await authApi.me();
      setUser(res.data.user);
      return res.data;
    },
    enabled: !!token,
    staleTime: 5 * 60 * 1000,
    retry: false,
  });
}

// Re-export so callers only need one import.
export { ApiError };
