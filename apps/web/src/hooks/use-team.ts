'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { teamApi, ApiError } from '@/lib/api';
import type { InviteMemberPayload, MembershipRole } from '@replyiq/api-client';

const MEMBERS_KEY      = ['team', 'members']    as const;
const INVITATIONS_KEY  = ['team', 'invitations'] as const;

export function useMembers() {
  return useQuery({
    queryKey: MEMBERS_KEY,
    queryFn:  () => teamApi.listMembers(),
  });
}

export function usePendingInvitations() {
  return useQuery({
    queryKey: INVITATIONS_KEY,
    queryFn:  () => teamApi.listInvitations(),
  });
}

export function useInviteMember() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: InviteMemberPayload) => teamApi.invite(payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: INVITATIONS_KEY });
      toast.success('Invitation sent');
    },
    onError: (err) => {
      toast.error(err instanceof ApiError ? err.message : 'Failed to send invitation');
    },
  });
}

export function useUpdateRole() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ userId, role }: { userId: string; role: MembershipRole }) =>
      teamApi.updateRole(userId, role),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: MEMBERS_KEY });
      toast.success('Role updated');
    },
    onError: (err) => {
      toast.error(err instanceof ApiError ? err.message : 'Failed to update role');
    },
  });
}

export function useRemoveMember() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (userId: string) => teamApi.removeMember(userId),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: MEMBERS_KEY });
      toast.success('Member removed');
    },
    onError: (err) => {
      toast.error(err instanceof ApiError ? err.message : 'Failed to remove member');
    },
  });
}

export function useCancelInvitation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => teamApi.cancelInvitation(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: INVITATIONS_KEY });
      toast.success('Invitation cancelled');
    },
    onError: (err) => {
      toast.error(err instanceof ApiError ? err.message : 'Failed to cancel invitation');
    },
  });
}
