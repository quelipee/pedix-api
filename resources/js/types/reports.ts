export type CourierReport = {
    deliveries: number;
    deliveryFees: string;
    unassignedDeliveries: number;
    rows: {
        id: number;
        name: string;
        active: boolean;
        deliveries: number;
        deliveryFees: string;
        averageFee: string;
        ordersTotal: string;
        averageTicket: string;
        averageDeliveryMinutes: number | null;
        timedDeliveries: number;
        recentDeliveries: {
            id: number;
            number: number;
            finalizedAt: string | null;
            deliveryFee: string;
            total: string;
        }[];
    }[];
};
