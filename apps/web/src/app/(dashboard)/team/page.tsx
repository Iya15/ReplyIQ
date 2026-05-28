'use client';

import { useState } from 'react';
import { UserPlus, MoreHorizontal, Trash2, LogOut } from 'lucide-react';
import { format } from 'date-fns';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  useMembers,
  usePendingInvitations,
  useInviteMember,
  useUpdateRole,
  useRemoveMember,
  useCancelInvitation,
} from '@/hooks/use-team';
import { useMe, useLogout } from '@/hooks/use-auth';
import { useAuthStore } from '@/lib/auth/auth-store';
import type { Member, MembershipRole } from '@replyiq/api-client';

const ROLES: MembershipRole[] = ['owner', 'admin', 'member'];

const ROLE_LABELS: Record<MembershipRole, string> = {
  owner:  'Owner',
  admin:  'Admin',
  member: 'Member',
};

const ROLE_BADGE: Record<MembershipRole, string> = {
  owner:  'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
  admin:  'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  member: 'bg-muted text-muted-foreground',
};

function getInitials(name: string): string {
  return name.split(' ').map((n) => n[0]).slice(0, 2).join('').toUpperCase();
}

// ── Invite dialog ─────────────────────────────────────────────────────────────

function InviteDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [email, setEmail] = useState('');
  const [role, setRole]   = useState<MembershipRole>('member');
  const invite            = useInviteMember();

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    invite.mutate({ email, role }, {
      onSuccess: () => { setEmail(''); setRole('member'); onClose(); },
    });
  }

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>Invite team member</DialogTitle>
        </DialogHeader>
        <form onSubmit={handleSubmit} className="space-y-4 pt-2">
          <div className="space-y-1.5">
            <Label htmlFor="invite-email">Email address</Label>
            <Input
              id="invite-email"
              type="email"
              placeholder="colleague@company.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              autoFocus
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="invite-role">Role</Label>
            <select
              id="invite-role"
              value={role}
              onChange={(e) => setRole(e.target.value as MembershipRole)}
              className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            >
              <option value="member">Member — can view and chat</option>
              <option value="admin">Admin — can invite and manage</option>
              <option value="owner">Owner — full access</option>
            </select>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
            <Button type="submit" disabled={invite.isPending}>
              {invite.isPending ? 'Sending…' : 'Send invitation'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

// ── Main page ─────────────────────────────────────────────────────────────────

export default function TeamPage() {
  const [inviteOpen, setInviteOpen] = useState(false);
  const [removeTarget, setRemoveTarget] = useState<Member | null>(null);

  const { data: meData }   = useMe();
  const { data: members, isLoading: membersLoading } = useMembers();
  const { data: pending,  isLoading: pendingLoading } = usePendingInvitations();
  const updateRole         = useUpdateRole();
  const removeMember       = useRemoveMember();
  const cancelInvitation   = useCancelInvitation();
  const logout             = useLogout();
  const currentUser        = useAuthStore((s) => s.user);

  const myRole       = meData?.role ?? 'member';
  const isOwner      = myRole === 'owner';
  const isAdminPlus  = myRole === 'owner' || myRole === 'admin';

  const memberList      = members?.data ?? [];
  const invitationList  = pending?.data ?? [];

  function handleRemove(m: Member) {
    removeMember.mutate(m.id, { onSuccess: () => setRemoveTarget(null) });
  }

  function handleLeave() {
    if (!currentUser) return;
    removeMember.mutate(currentUser.id, {
      onSuccess: () => { logout.mutate(); },
    });
  }

  return (
    <div className="p-6 max-w-4xl mx-auto space-y-8">
      {/* Header */}
      <div className="flex items-start justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-xl font-semibold">Team</h1>
          <p className="text-sm text-muted-foreground mt-0.5">
            Manage your organization members and invitations.
          </p>
        </div>
        <div className="flex items-center gap-2">
          {isAdminPlus && (
            <Button onClick={() => setInviteOpen(true)}>
              <UserPlus className="mr-2 h-4 w-4" />
              Invite member
            </Button>
          )}
          {myRole !== 'owner' && (
            <Button variant="outline" className="text-destructive border-destructive/30 hover:bg-destructive/10" onClick={handleLeave}>
              <LogOut className="mr-2 h-4 w-4" />
              Leave org
            </Button>
          )}
        </div>
      </div>

      {/* Members table */}
      <section>
        <h2 className="text-sm font-semibold text-muted-foreground uppercase tracking-wide mb-3">
          Members
        </h2>
        <div className="rounded-xl border overflow-hidden">
          {membersLoading ? (
            <div className="p-4 space-y-3">
              {Array.from({ length: 3 }).map((_, i) => (
                <Skeleton key={i} className="h-10 w-full" />
              ))}
            </div>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b bg-muted/40">
                  <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide">Member</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide">Role</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide hidden sm:table-cell">Joined</th>
                  <th className="px-4 py-3 w-10" />
                </tr>
              </thead>
              <tbody>
                {memberList.map((m) => {
                  const isSelf    = m.id === currentUser?.id;
                  const canChange = isOwner && !isSelf;
                  const canRemove = (isAdminPlus && !isSelf) || (isSelf && myRole !== 'owner');

                  return (
                    <tr key={m.id} className="border-b last:border-0 hover:bg-muted/20 transition-colors">
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-3">
                          <Avatar className="h-7 w-7 shrink-0">
                            <AvatarFallback className="text-xs bg-primary/10 text-primary">
                              {getInitials(m.name)}
                            </AvatarFallback>
                          </Avatar>
                          <div className="min-w-0">
                            <p className="font-medium truncate">{m.name}{isSelf && <span className="ml-1.5 text-xs text-muted-foreground">(you)</span>}</p>
                            <p className="text-xs text-muted-foreground truncate">{m.email}</p>
                          </div>
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        {canChange ? (
                          <select
                            value={m.role}
                            onChange={(e) => updateRole.mutate({ userId: m.id, role: e.target.value as MembershipRole })}
                            className="rounded-md border border-input bg-background px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-ring"
                          >
                            {ROLES.map((r) => (
                              <option key={r} value={r}>{ROLE_LABELS[r]}</option>
                            ))}
                          </select>
                        ) : (
                          <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${ROLE_BADGE[m.role]}`}>
                            {ROLE_LABELS[m.role]}
                          </span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-muted-foreground hidden sm:table-cell">
                        {m.joined_at ? format(new Date(m.joined_at), 'MMM d, yyyy') : '—'}
                      </td>
                      <td className="px-4 py-3">
                        {canRemove && (
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button size="icon" variant="ghost" className="h-7 w-7">
                                <MoreHorizontal className="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                              <DropdownMenuItem
                                className="text-destructive focus:text-destructive"
                                onClick={() => setRemoveTarget(m)}
                              >
                                <Trash2 className="mr-2 h-4 w-4" />
                                {isSelf ? 'Leave organization' : 'Remove member'}
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          )}
        </div>
      </section>

      {/* Pending invitations */}
      {isAdminPlus && (
        <section>
          <h2 className="text-sm font-semibold text-muted-foreground uppercase tracking-wide mb-3">
            Pending Invitations
          </h2>
          {pendingLoading ? (
            <Skeleton className="h-24 w-full rounded-xl" />
          ) : invitationList.length === 0 ? (
            <p className="text-sm text-muted-foreground py-4">No pending invitations.</p>
          ) : (
            <div className="rounded-xl border overflow-hidden">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b bg-muted/40">
                    <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide">Email</th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide">Role</th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide hidden sm:table-cell">Expires</th>
                    <th className="px-4 py-3 w-10" />
                  </tr>
                </thead>
                <tbody>
                  {invitationList.map((inv) => (
                    <tr key={inv.id} className="border-b last:border-0 hover:bg-muted/20">
                      <td className="px-4 py-3 font-medium">{inv.email}</td>
                      <td className="px-4 py-3">
                        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${ROLE_BADGE[inv.role]}`}>
                          {ROLE_LABELS[inv.role]}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-muted-foreground hidden sm:table-cell">
                        {format(new Date(inv.expires_at), 'MMM d, yyyy')}
                      </td>
                      <td className="px-4 py-3">
                        <Button
                          size="icon"
                          variant="ghost"
                          className="h-7 w-7 text-muted-foreground hover:text-destructive"
                          onClick={() => cancelInvitation.mutate(inv.id)}
                          title="Cancel invitation"
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      )}

      {/* Remove confirmation dialog */}
      <Dialog open={!!removeTarget} onOpenChange={() => setRemoveTarget(null)}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle>Remove {removeTarget?.name}?</DialogTitle>
          </DialogHeader>
          <p className="text-sm text-muted-foreground">
            They will lose access to this organization immediately.
          </p>
          <DialogFooter className="pt-2">
            <Button variant="outline" onClick={() => setRemoveTarget(null)}>Cancel</Button>
            <Button
              variant="destructive"
              disabled={removeMember.isPending}
              onClick={() => removeTarget && handleRemove(removeTarget)}
            >
              {removeMember.isPending ? 'Removing…' : 'Remove'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <InviteDialog open={inviteOpen} onClose={() => setInviteOpen(false)} />
    </div>
  );
}
