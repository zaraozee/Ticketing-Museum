export interface User {
    id: number;
    uuid: string;
    name: string;
    email: string;
    phone?: string | null;
    roles: string[];
    is_admin: boolean;
    is_organizer: boolean;
    organizer_profile?: {
        organization_name: string;
        status: 'pending' | 'approved' | 'suspended';
    } | null;
}

export interface SharedProps {
    appName: string;
    auth: {
        user: User | null;
    };
    flash: {
        success?: string | null;
        error?: string | null;
        status?: string | null;
    };
    errors: Record<string, string>;
}
