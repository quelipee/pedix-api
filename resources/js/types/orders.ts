export type OrderStatus =
    | 'novo'
    | 'em_preparo'
    | 'pronto'
    | 'finalizado'
    | 'cancelado';

export type OrderDetails = {
    id: number;
    customerId: number | null;
    modality: 'local' | 'retirada' | 'entrega';
    modalityLabel: string;
    status: OrderStatus;
    statusLabel: string;
    nextStatus?: OrderStatus | null;
    createdAt: string | null;
    finalizedAt: string | null;
    cancelledAt: string | null;
    cancellationReason: string | null;
    cancelledBy: { id: number; name: string } | null;
    customerName: string | null;
    customerPhone: string | null;
    paymentMethodLabel: string | null;
    changeFor: string | null;
    deliveryFee: string | null;
    changeAmount: string | null;
    deliveryAddress: {
        postalCode: string;
        street: string;
        number: string;
        complement: string | null;
        neighborhood: string;
        city: string;
        state: string;
    } | null;
    courier: {
        id: number;
        name: string;
        phone: string;
    } | null;
    dispatchedAt: string | null;
    total: string;
    items: {
        id: number;
        productName: string;
        quantity: number;
        unitPrice: string;
        notes: string | null;
        selectedOptions: {
            id: number;
            group: string;
            name: string;
            price: string;
        }[];
    }[];
};

export type Courier = {
    id: number;
    name: string;
    phone: string;
    active: boolean;
    available: boolean;
    activeDeliveriesCount: number;
};
