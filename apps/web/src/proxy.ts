import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

const DASHBOARD_PREFIXES = [
  '/dashboard',
  '/chatbots',
  '/conversations',
  '/analytics',
  '/team',
  '/settings',
];

const AUTH_PATHS = ['/login', '/register', '/forgot-password'];

export function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl;
  const token = request.cookies.get('auth_token')?.value;

  const isDashboard = DASHBOARD_PREFIXES.some(
    (p) => pathname === p || pathname.startsWith(`${p}/`),
  );
  const isAuth = AUTH_PATHS.includes(pathname);

  if (isDashboard && !token) {
    const url = request.nextUrl.clone();
    url.pathname = '/login';
    return NextResponse.redirect(url);
  }

  if (isAuth && token) {
    const url = request.nextUrl.clone();
    url.pathname = '/dashboard';
    return NextResponse.redirect(url);
  }

  return NextResponse.next();
}

export const config = {
  matcher: [
    '/dashboard/:path*',
    '/chatbots/:path*',
    '/conversations/:path*',
    '/analytics/:path*',
    '/team/:path*',
    '/settings/:path*',
    '/login',
    '/register',
    '/forgot-password',
  ],
};
