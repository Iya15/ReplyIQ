import type { ApiClient } from '../client';
import type {
  AcceptInvitationPayload,
  ApiResponse,
  InvitationPreview,
  InviteMemberPayload,
  Member,
  MembershipRole,
  PendingInvitation,
  User,
} from '../types';

export function createTeamEndpoints(client: ApiClient) {
  return {
    // GET /organizations/current/members
    listMembers: () =>
      client.get<ApiResponse<Member[]>>('/organizations/current/members'),

    // PATCH /organizations/current/members/{userId}
    updateRole: (userId: string, role: MembershipRole) =>
      client.patch<ApiResponse<{ message: string }>>(`/organizations/current/members/${userId}`, { role }),

    // DELETE /organizations/current/members/{userId}
    removeMember: (userId: string) =>
      client.delete<ApiResponse<{ message: string }>>(`/organizations/current/members/${userId}`),

    // GET /organizations/current/invitations
    listInvitations: () =>
      client.get<ApiResponse<PendingInvitation[]>>('/organizations/current/invitations'),

    // POST /organizations/current/invitations
    invite: (payload: InviteMemberPayload) =>
      client.post<ApiResponse<PendingInvitation>>('/organizations/current/invitations', payload),

    // DELETE /organizations/current/invitations/{id}
    cancelInvitation: (id: string) =>
      client.delete<ApiResponse<{ message: string }>>(`/organizations/current/invitations/${id}`),

    // GET /invitations/{token}  (public)
    getInvitation: (token: string) =>
      client.get<ApiResponse<InvitationPreview>>(`/invitations/${token}`),

    // POST /invitations/{token}/accept  (public)
    acceptInvitation: (token: string, payload: AcceptInvitationPayload) =>
      client.post<ApiResponse<{ token: string; user: User }>>(`/invitations/${token}/accept`, payload),
  };
}
