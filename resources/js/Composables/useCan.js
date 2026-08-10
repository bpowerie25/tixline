import { usePage } from '@inertiajs/vue3';

export function useCan() {
    const page = usePage();

    function can(permission) {
        const permissions = page.props.auth?.can || [];
        return permissions.includes(permission);
    }

    function canAny(permissions) {
        return permissions.some(p => can(p));
    }

    return { can, canAny };
}
