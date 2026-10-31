import { normalizePhone } from './text-links.ts';

export const INVITE_LIFETIME_MS = 30 * 24 * 60 * 60 * 1000;
export const SESSION_LIFETIME_MS = 14 * 24 * 60 * 60 * 1000;
export type InvitationGrant = {
  playerId: string;
  phone: string;
  tokenHash: string;
  version: number;
  expiresAt: number;
};
export type PlayerSession = {
  playerId: string;
  tokenHash: string;
  invitationVersion: number;
  expiresAt: number;
};

function randomToken(): string {
  const bytes = crypto.getRandomValues(new Uint8Array(32));
  return btoa(String.fromCharCode(...bytes)).replaceAll('+', '-').replaceAll('/', '_').replaceAll('=', '');
}
export async function hashToken(token: string): Promise<string> {
  const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(token));
  return Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, '0')).join('');
}
function sameHash(a: string, b: string): boolean {
  // Always compare the full fixed-length hash, rather than exit on a matching prefix.
  if(a.length !== 64 || b.length !== 64) return false;
  let diff = 0;
  for(let i = 0; i < 64; i++) diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return diff === 0;
}
export async function createInvitation(playerId: string, phone: string, previousVersion = 0, now = Date.now()) {
  const normalized = normalizePhone(phone);
  if(!normalized) throw new Error('Add the player’s phone number before creating an invitation.');
  const token = randomToken();
  const grant: InvitationGrant = {
    playerId, phone: normalized, tokenHash: await hashToken(token),
    version: previousVersion + 1, expiresAt: now + INVITE_LIFETIME_MS,
  };
  return { token, grant };
}
export function invitationUrl(origin: string, token: string, game='2026_01'): string {
  const url = new URL(origin);
  if(!/^https?:$/.test(url.protocol)) throw new Error('Use an HTTP or HTTPS site address.');
  if(!/^\d{4}_\d{2}$/.test(game)||game.endsWith('_00'))throw new Error('Invalid game code.');
  url.searchParams.set('game',game);url.searchParams.set('login',token);url.hash='';
  return url.toString();
}
export async function verifyInvitation(grant: InvitationGrant | null, token: string, phone: string, now = Date.now()): Promise<boolean> {
  if(!grant || grant.expiresAt <= now || !/^[A-Za-z0-9_-]{43}$/.test(token)) return false;
  let normalized: string;
  try { normalized = normalizePhone(phone); } catch { return false; }
  if(!normalized || normalized !== grant.phone) return false;
  return sameHash(await hashToken(token), grant.tokenHash);
}
export async function createPlayerSession(grant: InvitationGrant, now = Date.now()) {
  const token = randomToken();
  const session: PlayerSession = {
    playerId: grant.playerId, tokenHash: await hashToken(token),
    invitationVersion: grant.version, expiresAt: now + SESSION_LIFETIME_MS,
  };
  return { token, session };
}
export async function verifyPlayerSession(session: PlayerSession | null, grant: InvitationGrant | null, cookie: string, now = Date.now()): Promise<boolean> {
  if(!session || !grant || session.expiresAt <= now || session.playerId !== grant.playerId || session.invitationVersion !== grant.version || !/^[A-Za-z0-9_-]{43}$/.test(cookie)) return false;
  // Existing sessions can outlive an invitation's sign-in window; explicit rotation revokes them.
  return sameHash(await hashToken(cookie), session.tokenHash);
}
