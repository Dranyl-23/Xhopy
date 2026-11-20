<script setup lang="ts" generic="T extends string | number = string">
import type { AcceptableValue } from "reka-ui"
import { computed, type HTMLAttributes } from "vue"
import { cn } from "@shared/lib/utils"
import Select from "./Select.vue"
import SelectContent from "./SelectContent.vue"
import SelectItem from "./SelectItem.vue"
import SelectTrigger from "./SelectTrigger.vue"
import SelectValue from "./SelectValue.vue"

export interface SelectOption<V extends string | number = string> {
  value: V
  label: string
  disabled?: boolean
}

const EMPTY_SENTINEL = "__xhopy_empty__"

const props = withDefaults(
  defineProps<{
    id?: string
    modelValue?: T | "" | null
    options: ReadonlyArray<SelectOption<T>>
    placeholder?: string
    emptyOptionLabel?: string
    ariaLabel?: string
    size?: "sm" | "default" | "lg"
    disabled?: boolean
    required?: boolean
    triggerClass?: HTMLAttributes["class"]
    contentClass?: HTMLAttributes["class"]
  }>(),
  {
    size: "lg",
    disabled: false,
    required: false,
  },
)

const emit = defineEmits<{
  (e: "update:modelValue", value: T): void
  (e: "change", value: T): void
}>()

const internalValue = computed<string>(() => {
  if (
    props.modelValue === undefined ||
    props.modelValue === null ||
    props.modelValue === ""
  ) {
    return props.emptyOptionLabel !== undefined ? EMPTY_SENTINEL : ""
  }

  return String(props.modelValue)
})

function handleValueChange(next: AcceptableValue): void {
  const normalized = next === null || next === undefined ? "" : String(next)

  if (normalized === EMPTY_SENTINEL) {
    emit("update:modelValue", "" as T)
    emit("change", "" as T)
    return
  }

  const matched = props.options.find(
    (option) => String(option.value) === normalized,
  )
  const resolved = (matched ? matched.value : normalized) as T

  emit("update:modelValue", resolved)
  emit("change", resolved)
}
</script>

<template>
  <Select
    :model-value="internalValue"
    :disabled="disabled"
    :required="required"
    @update:model-value="handleValueChange"
  >
    <SelectTrigger
      :id="id"
      :size="size"
      :aria-label="ariaLabel"
      :class="cn('w-full', triggerClass)"
    >
      <SelectValue :placeholder="placeholder ?? emptyOptionLabel" />
    </SelectTrigger>
    <SelectContent :class="contentClass">
      <SelectItem v-if="emptyOptionLabel !== undefined" :value="EMPTY_SENTINEL">
        {{ emptyOptionLabel }}
      </SelectItem>
      <SelectItem
        v-for="option in options"
        :key="String(option.value)"
        :value="String(option.value)"
        :disabled="option.disabled"
      >
        {{ option.label }}
      </SelectItem>
    </SelectContent>
  </Select>
</template>
