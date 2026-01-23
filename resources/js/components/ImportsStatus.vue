<template>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left border-b">
                    <th class="py-2">File</th>
                    <th class="py-2">Status</th>
                    <th class="py-2">Progress</th>
                    <th class="py-2">Updated</th>
                </tr>
            </thead>
            <tbody>
                <template v-if="imports.length">
                    <template v-for="importItem in imports" :key="importItem.id">
                        <tr class="border-b">
                            <td class="py-2">{{ importItem.original_name }}</td>
                            <td class="py-2 capitalize">{{ importItem.status }}</td>
                            <td class="py-2">
                                <div class="flex flex-col gap-1">
                                    <div>
                                        {{ importItem.processed_rows ?? 0 }}
                                        <span v-if="importItem.total_rows">/ {{ importItem.total_rows }}</span>
                                    </div>
                                    <div class="h-2 w-full rounded bg-gray-200 overflow-hidden">
                                        <div
                                            class="h-full"
                                            :class="progressClass(importItem)"
                                            :style="{ width: progressPercent(importItem) + '%' }"
                                        ></div>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ progressPercent(importItem) }}%
                                    </div>
                                </div>
                            </td>
                            <td class="py-2">{{ formatDate(importItem.updated_at) }}</td>
                        </tr>
                        <tr v-if="importItem.error" class="border-b bg-red-50">
                            <td colspan="4" class="py-2 text-red-600">
                                {{ importItem.error }}
                            </td>
                        </tr>
                    </template>
                </template>
                <tr v-else>
                    <td colspan="4" class="py-4 text-gray-500">No imports yet.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
export default {
    name: 'ImportsStatus',
    props: {
        initialImports: {
            type: Array,
            default: () => [],
        },
        pollInterval: {
            type: Number,
            default: 5000,
        },
    },
    data() {
        return {
            imports: [...this.initialImports],
            timer: null,
        };
    },
    mounted() {
        this.refresh();
        this.timer = window.setInterval(this.refresh, this.pollInterval);
    },
    beforeUnmount() {
        if (this.timer) {
            window.clearInterval(this.timer);
            this.timer = null;
        }
    },
    methods: {
        formatDate(value) {
            if (!value) return '';
            const date = new Date(value);
            return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
        },
        progressPercent(item) {
            const total = Number(item.total_rows || 0);
            const processed = Number(item.processed_rows || 0);
            if (!total || total <= 0) {
                return 0;
            }

            return Math.min(100, Math.max(0, Math.round((processed / total) * 100)));
        },
        progressClass(item) {
            const percent = this.progressPercent(item);
            if (percent >= 100) {
                return 'bg-green-600';
            }
            if (percent >= 30) {
                return 'bg-orange-500';
            }
            return 'bg-red-600';
        },
        async refresh() {
            const updates = await Promise.all(
                this.imports.map(async (item) => {
                    try {
                        const response = await fetch(`/imports/${item.id}`, {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });

                        if (!response.ok) {
                            return item;
                        }

                        return await response.json();
                    } catch (error) {
                        return item;
                    }
                })
            );

            this.imports = updates;
        },
    },
};
</script>
