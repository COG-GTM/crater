<template>
  <div>
    <TabGroup
      :key="tabGroupKey"
      :default-index="currentIndex"
      @change="onChange"
    >
      <TabList
        :class="[
          'flex border-b border-grey-light',
          'relative overflow-x-auto overflow-y-hidden',
          'lg:pb-0 lg:ml-0',
        ]"
      >
        <Tab
          v-for="(tab, index) in tabs"
          v-slot="{ selected }"
          :key="index"
          as="template"
        >
          <button
            :class="[
              'px-8 py-2 text-sm leading-5 font-medium flex items-center relative border-b-2 mt-4 focus:outline-none whitespace-nowrap',
              selected
                ? ' border-primary-400 text-black font-medium'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
            ]"
          >
            {{ tab.title }}

            <BaseBadge
              v-if="tab.count"
              class="!rounded-full overflow-hidden ml-2"
              :variant="tab['count-variant']"
              default-class="flex items-center justify-center w-5 h-5 p-1 rounded-full text-medium"
            >
              {{ tab.count }}
            </BaseBadge>
          </button>
        </Tab>
      </TabList>

      <slot name="before-tabs" />

      <TabPanels>
        <slot />
      </TabPanels>
    </TabGroup>
  </div>
</template>

<script setup>
import { computed, ref, useSlots, watch } from 'vue'
import { TabGroup, TabList, Tab, TabPanels } from '@headlessui/vue'

const props = defineProps({
  defaultIndex: {
    type: Number,
    default: 0,
  },
  filter: {
    type: String,
    default: null,
  },
  selectedIndex: {
    type: Number,
    default: null,
  },
})

const emit = defineEmits(['change'])

const slots = useSlots()

const tabs = computed(() => slots.default().map((tab) => tab.props))

const currentIndex = ref(props.selectedIndex ?? props.defaultIndex)
const tabGroupKey = ref(0)

// headlessui's TabGroup only reads its index on mount, so remount it when the
// parent selects a different tab (e.g. from a filter outside the tab bar).
watch(
  () => props.selectedIndex,
  (index) => {
    if (index === null || index < 0 || index === currentIndex.value) {
      return
    }

    currentIndex.value = index
    tabGroupKey.value++
  }
)

function onChange(d) {
  currentIndex.value = d
  emit('change', tabs.value[d])
}
</script>
