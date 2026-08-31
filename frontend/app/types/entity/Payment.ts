import type { PaymentState } from '~/types/enum/PaymentState';

export type Payment = {
    id: number;
    amount: number;
    state: PaymentState;
    helloAssoCheckoutIntentId: number | null;
    helloAssoOrderId: number | null;
    helloAssoPaymentId: number | null;
    paymentReceiptUrl: string | null;
    createdAt: string;
    updatedAt: string;
};
