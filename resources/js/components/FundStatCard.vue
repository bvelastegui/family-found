<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
  Card,
  CardAction,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';

defineProps<{
  label: string;
  value: string;
  badge: string;
  headline: string;
  detail: string;
}>();
</script>

<template>
  <Card
    class="@container/card bg-linear-to-t from-primary/5 to-card shadow-xs dark:from-primary/10"
  >
    <CardHeader>
      <CardDescription>{{ label }}</CardDescription>
      <CardTitle
        class="text-2xl font-semibold tabular-nums @[250px]/card:text-3xl"
      >
        {{ value }}
      </CardTitle>
      <CardAction>
        <Badge variant="outline">{{ badge }}</Badge>
      </CardAction>
    </CardHeader>
    <CardFooter class="flex-col items-start gap-1.5 text-sm">
      <p class="font-medium">{{ headline }}</p>
      <p class="text-muted-foreground">{{ detail }}</p>
      <slot name="footer" />
    </CardFooter>
  </Card>
</template>
