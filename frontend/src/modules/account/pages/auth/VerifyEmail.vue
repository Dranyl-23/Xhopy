<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { MailCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@shared/components/InputError.vue';
import TextLink from '@shared/components/TextLink.vue';
import { Button } from '@shared/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@shared/components/ui/input-otp';
import { Label } from '@shared/components/ui/label';
import { Spinner } from '@shared/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import {
    send as sendCode,
    verify as verifyCode,
} from '@/routes/verification/code';

defineOptions({
    layout: {
        title: 'Email verification',
        description:
            'Verify your email address by sending and entering a 6-digit verification code or opening the link sent to your inbox.',
    },
});

const props = withDefaults(
    defineProps<{
        status?: string;
        email?: string;
        isGmail?: boolean;
        codeExpiresInMinutes?: number;
    }>(),
    {
        codeExpiresInMinutes: 10,
    },
);

const page = usePage();
const code = ref<string>('');
const recipientEmail = computed<string>(
    () => props.email || page.props.auth.user?.email || '',
);
</script>

<template>
    <Head title="Email verification" />

    <div class="space-y-6">
        <div
            v-if="recipientEmail"
            class="rounded-md border border-border bg-muted/40 px-4 py-3 text-center text-sm text-muted-foreground"
        >
            <span v-if="isGmail">Gmail verification address: </span>
            <span v-else>Verification address: </span>
            <span class="font-medium text-foreground">{{
                recipientEmail
            }}</span>
        </div>

        <div
            v-if="status === 'verification-code-sent'"
            class="flex items-start gap-2 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-400"
        >
            <MailCheck class="mt-0.5 size-4 shrink-0" />
            <span>
                A 6-digit verification code has been sent to
                <strong>{{ recipientEmail }}</strong
                >. It expires in {{ codeExpiresInMinutes }} minutes.
            </span>
        </div>

        <div
            v-else-if="status === 'verification-link-sent'"
            class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-center text-sm font-medium text-green-700 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-400"
        >
            A new verification link has been sent to the email address you
            provided during registration.
        </div>

        <Form
            v-bind="sendCode.form()"
            class="space-y-2 text-center"
            #default="{ errors, processing }"
        >
            <Button type="submit" class="w-full" :disabled="processing">
                <Spinner v-if="processing" />
                {{
                    status === 'verification-code-sent'
                        ? 'Resend verification code'
                        : isGmail
                          ? 'Send verification code to Gmail'
                          : 'Send verification code'
                }}
            </Button>
            <InputError :message="errors.email" />
        </Form>

        <Form
            v-bind="verifyCode.form()"
            class="space-y-4 border-t border-border pt-5"
            reset-on-error
            @error="code = ''"
            #default="{ errors, processing }"
        >
            <input type="hidden" name="code" :value="code" />

            <div
                class="flex flex-col items-center justify-center space-y-3 text-center"
            >
                <Label for="verification-code" class="text-sm font-medium">
                    Enter 6-digit verification code
                </Label>
                <div class="flex w-full items-center justify-center">
                    <InputOTP
                        id="verification-code"
                        v-model="code"
                        :maxlength="6"
                        :disabled="processing"
                        autofocus
                    >
                        <InputOTPGroup>
                            <InputOTPSlot
                                v-for="index in 6"
                                :key="index"
                                :index="index - 1"
                            />
                        </InputOTPGroup>
                    </InputOTP>
                </div>
                <InputError :message="errors.code" />
            </div>

            <Button
                type="submit"
                variant="secondary"
                class="w-full"
                :disabled="processing || code.length !== 6"
            >
                <Spinner v-if="processing" />
                Verify code
            </Button>
        </Form>

        <div class="space-y-4 border-t border-border pt-4 text-center">
            <Form v-bind="send.form()" #default="{ processing }">
                <Button
                    type="submit"
                    variant="ghost"
                    size="sm"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    Resend verification link instead
                </Button>
            </Form>

            <TextLink
                :href="logout()"
                as="button"
                class="mx-auto block text-sm"
            >
                Log out
            </TextLink>
        </div>
    </div>
</template>
