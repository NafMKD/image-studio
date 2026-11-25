<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    CircleUserRound,
    Image,
    LayoutDashboard,
    ShieldCheck,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

const page = usePage();
const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Studio',
        href: '/studio',
        icon: Image,
    },
    {
        title: 'Dashboard',
        href: '/account',
        icon: LayoutDashboard,
    },
    {
        title: 'Profile',
        href: '/settings/profile',
        icon: CircleUserRound,
    },
    ...(page.props.auth.user.is_admin
        ? [{ title: 'Super admin', href: '/admin', icon: ShieldCheck }]
        : []),
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/studio">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>
    </Sidebar>
    <slot />
</template>
