export type CustomerAddress = {
    postalCode: string;
    street: string;
    number: string;
    complement: string | null;
    neighborhood: string;
    city: string;
    state: string;
};

export type Customer = {
    id: number;
    name: string;
    phone: string;
    address: CustomerAddress | null;
    ordersCount?: number;
    lastOrderAt?: string | null;
    createdAt: string | null;
    popularProduct?: { name: string; quantity: number } | null;
};
