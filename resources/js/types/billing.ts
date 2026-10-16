export type SubscriptionNoticeData = {
    status: string;
    title: string;
    message: string;
    tone: 'neutral' | 'warning';
    url: string;
};
