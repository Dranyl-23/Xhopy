<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { History, Laptop, LogOut, ShieldAlert } from '@lucide/vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import type { Props as ManagePasskeysProps } from '@modules/account/components/ManagePasskeys.vue';
import ManagePasskeys from '@modules/account/components/ManagePasskeys.vue';
import type { Props as ManageTwoFactorProps } from '@modules/account/components/ManageTwoFactor.vue';
import ManageTwoFactor from '@modules/account/components/ManageTwoFactor.vue';
import Heading from '@shared/components/Heading.vue';
import InputError from '@shared/components/InputError.vue';
import PasswordInput from '@shared/components/PasswordInput.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Label } from '@shared/components/ui/label';
import { edit } from '@/routes/security';
import {
    destroy as destroySession,
    destroyOthers as destroyOtherSessions,
} from '@/routes/security/sessions';
import type { ActiveSession, SecurityAuditEntry } from '@modules/account/types';

// oxfmt-ignore
type Props = {
    passwordRules: string;
    activeSessions?: ActiveSession[];
    securityEvents?: SecurityAuditEntry[];
} & ManagePasskeysProps &
    ManageTwoFactorProps;

const props = withDefaults(defineProps<Props>(), {
    activeSessions: () => [],
    securityEvents: () => [],
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Security settings',
                href: edit(),
            },
        ],
    },
});

function signOutSession(sessionId: string): void {
    router.delete(destroySession.url(sessionId), {
        preserveScroll: true,
    });
}

function signOutOtherSessions(): void {
    router.delete(destroyOtherSessions.url(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Security settings" />

    <h1 class="sr-only">Security settings</h1>

    <div
        v-if="$page.props.auth?.mfaRecommended"
        class="flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200"
    >
        <ShieldAlert
            class="mt-0.5 size-5 shrink-0 text-amber-600"
            aria-hidden="true"
        />
        <div>
            <p class="font-semibold">Privileged account MFA recommendation</p>
            <p class="mt-0.5 text-xs opacity-90">
                Your account holds elevated permissions. Enable two-factor
                authentication or register a passkey below to protect storefront
                administration.
            </p>
        </div>
    </div>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Update password"
            description="Ensure your account is using a long, random password to stay secure"
        />

        <Form
            v-bind="SecurityController.update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="current_password">Current password</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                    placeholder="Current password"
                />
                <InputError :message="errors.current_password" />
            </div>

            <div class="grid gap-2">
                <Label for="password">New password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="New password"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="Confirm password"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-password-button"
                >
                    Save
                </Button>
            </div>
        </Form>
    </div>

    <ManageTwoFactor
        :canManageTwoFactor="canManageTwoFactor"
        :requiresConfirmation="requiresConfirmation"
        :twoFactorEnabled="twoFactorEnabled"
    />

    <ManagePasskeys
        :canManagePasskeys="canManagePasskeys"
        :passkeys="passkeys"
    />

    <div class="space-y-5 border-t border-border pt-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                variant="small"
                title="Active sessions"
                description="Review signed-in browsers and revoke unfamiliar devices"
            />
            <Button
                v-if="activeSessions.some((s) => !s.is_current)"
                type="button"
                variant="outline"
                size="sm"
                class="h-9 gap-1.5"
                data-test="sign-out-other-sessions-button"
                @click="signOutOtherSessions"
            >
                <LogOut class="size-3.5" aria-hidden="true" />
                <span>Sign out other devices</span>
            </Button>
        </div>

        <div class="space-y-3">
            <div
                v-for="session in activeSessions"
                :key="session.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card px-4 py-3.5"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-9 items-center justify-center rounded-lg bg-muted text-muted-foreground"
                    >
                        <Laptop class="size-4" aria-hidden="true" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-foreground">
                                {{ session.device }}
                            </span>
                            <Badge v-if="session.is_current" variant="default">
                                This device
                            </Badge>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            IP {{ session.ip_address }} · Last active
                            {{ session.last_active_diff }}
                        </p>
                    </div>
                </div>

                <Button
                    v-if="!session.is_current"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-8 text-xs text-destructive hover:text-destructive"
                    @click="signOutSession(session.id)"
                >
                    Sign out
                </Button>
            </div>
        </div>
    </div>

    <div class="space-y-4 border-t border-border pt-6">
        <Heading
            variant="small"
            title="Recent security activity"
            description="Audit history for sign-ins, password updates, verification, and session changes"
        />

        <div
            v-if="securityEvents.length === 0"
            class="rounded-xl border border-dashed border-border p-4 text-sm text-muted-foreground"
        >
            No recent security events recorded yet.
        </div>

        <ul
            v-else
            class="divide-y divide-border rounded-xl border border-border"
        >
            <li
                v-for="entry in securityEvents"
                :key="entry.id"
                class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm"
            >
                <div class="flex items-center gap-2.5">
                    <History
                        class="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span class="font-medium text-foreground">
                        {{ entry.label }}
                    </span>
                </div>
                <span class="text-xs text-muted-foreground">
                    IP {{ entry.ip_address }} · {{ entry.created_at_diff }}
                </span>
            </li>
        </ul>
    </div>
</template>
