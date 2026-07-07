<script setup>
import { computed } from 'vue';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  BarElement,
  ArcElement
} from 'chart.js';
import { Line, Bar, Doughnut } from 'vue-chartjs';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  BarElement,
  ArcElement
);

const props = defineProps({
  chartData: Object
});

const lineChartData = computed(() => ({
  labels: props.chartData.labels,
  datasets: [
    {
      label: 'Người dùng mới',
      backgroundColor: '#10b981', // emerald-500
      borderColor: '#10b981',
      data: props.chartData.users,
      tension: 0.4
    },
    {
      label: 'Chiến dịch mới',
      backgroundColor: '#f59e0b', // amber-500
      borderColor: '#f59e0b',
      data: props.chartData.campaigns,
      tension: 0.4
    }
  ]
}));

const barChartData = computed(() => ({
  labels: props.chartData.labels,
  datasets: [
    {
      label: 'Bài đăng thực phẩm',
      backgroundColor: '#3b82f6', // blue-500
      data: props.chartData.food_posts
    }
  ]
}));

const donutChartData = computed(() => ({
  labels: props.chartData.transport_methods?.labels || ['Tự đến lấy', 'Nhờ người thân', 'Gọi xe công nghệ'],
  datasets: [
    {
      backgroundColor: ['#10b981', '#f59e0b', '#3b82f6'],
      data: props.chartData.transport_methods?.data || [0, 0, 0]
    }
  ]
}));

const donutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom'
    }
  }
};

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom'
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        stepSize: 1
      }
    }
  }
};
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col">
      <h3 class="text-sm font-bold text-gray-800 mb-4">Tăng trưởng (7 Ngày qua)</h3>
      <div class="h-64">
        <Line :data="lineChartData" :options="chartOptions" />
      </div>
    </div>
    
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col">
      <h3 class="text-sm font-bold text-gray-800 mb-4">Hoạt động (7 Ngày qua)</h3>
      <div class="h-64">
        <Bar :data="barChartData" :options="chartOptions" />
      </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col">
      <h3 class="text-sm font-bold text-gray-800 mb-4">Phương thức vận chuyển</h3>
      <div class="h-64">
        <Doughnut :data="donutChartData" :options="donutOptions" />
      </div>
    </div>
  </div>
</template>
