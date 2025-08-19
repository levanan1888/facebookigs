<x-layouts.app :title="__('Performance Dashboard Facebook Group Insight')">
    <div class="min-h-screen bg-white">
        @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Growth Chart
                const growthCtx = document.getElementById('growthChart');
                if (growthCtx) {
                    new Chart(growthCtx.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: @json($chartData['labels'] ?? []),
                            datasets: [
                                {
                                    label: 'Joined',
                                    data: @json($chartData['joined'] ?? []),
                                    backgroundColor: 'rgba(147, 51, 234, 0.6)',
                                    borderColor: 'rgba(147, 51, 234, 1)',
                                    borderWidth: 1,
                                    yAxisID: 'y'
                                },
                                {
                                    label: 'Posts',
                                    data: @json($chartData['posts'] ?? []),
                                    backgroundColor: 'rgba(59, 130, 246, 0.6)',
                                    borderColor: 'rgba(59, 130, 246, 1)',
                                    borderWidth: 1,
                                    yAxisID: 'y'
                                },
                                {
                                    label: 'Engagements',
                                    data: @json($chartData['engagements'] ?? []),
                                    type: 'line',
                                    borderColor: 'rgba(147, 197, 253, 1)',
                                    backgroundColor: 'rgba(147, 197, 253, 0.1)',
                                    borderWidth: 2,
                                    fill: false,
                                    yAxisID: 'y1'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false,
                            },
                            scales: {
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    title: {
                                        display: true,
                                        text: 'Joined | Posts'
                                    },
                                    max: 12.5
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    title: {
                                        display: true,
                                        text: 'Engagements'
                                    },
                                    max: 10,
                                    grid: {
                                        drawOnChartArea: false,
                                    },
                                },
                                x: {
                                    display: true
                                }
                            },
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top'
                                }
                            }
                        }
                    });
                }

                // Engagement Charts
                const postsCtx = document.getElementById('postsChart');
                if (postsCtx) {
                    new Chart(postsCtx.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: @json($engagementData['chartData']['labels'] ?? []),
                            datasets: [
                                {
                                    label: 'Posts',
                                    data: @json($engagementData['chartData']['posts'] ?? []),
                                    borderColor: 'rgba(59, 130, 246, 1)',
                                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                    borderWidth: 2,
                                    fill: false
                                },
                                {
                                    label: 'Posts (28 ngày trước)',
                                    data: @json($engagementData['chartData']['posts_28_days_ago'] ?? []),
                                    borderColor: 'rgba(147, 197, 253, 1)',
                                    backgroundColor: 'rgba(147, 197, 253, 0.1)',
                                    borderWidth: 2,
                                    fill: false
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }

                const engagementCtx = document.getElementById('engagementChart');
                if (engagementCtx) {
                    new Chart(engagementCtx.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: @json($engagementData['chartData']['labels'] ?? []),
                            datasets: [
                                {
                                    label: 'Engagement',
                                    data: @json($engagementData['chartData']['engagement'] ?? []),
                                    borderColor: 'rgba(59, 130, 246, 1)',
                                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                    borderWidth: 2,
                                    fill: false
                                },
                                {
                                    label: 'Engagement (28 ngày trước)',
                                    data: @json($engagementData['chartData']['engagement_28_days_ago'] ?? []),
                                    borderColor: 'rgba(147, 197, 253, 1)',
                                    backgroundColor: 'rgba(147, 197, 253, 0.1)',
                                    borderWidth: 2,
                                    fill: false
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }

                // Participants Charts
                const ageGenderCtx = document.getElementById('ageGenderChart');
                if (ageGenderCtx) {
                    new Chart(ageGenderCtx.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: @json($participantsData['ageGender']['labels'] ?? []),
                            datasets: [
                                {
                                    label: 'Woman',
                                    data: @json($participantsData['ageGender']['women'] ?? []),
                                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                                    borderColor: 'rgba(59, 130, 246, 1)',
                                    borderWidth: 1
                                },
                                {
                                    label: 'Man',
                                    data: @json($participantsData['ageGender']['men'] ?? []),
                                    backgroundColor: 'rgba(147, 51, 234, 0.8)',
                                    borderColor: 'rgba(147, 51, 234, 1)',
                                    borderWidth: 1
                                }
                            ]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top'
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    max: 30
                                }
                            }
                        }
                    });
                }

                const engagementMetricsCtx = document.getElementById('engagementMetricsChart');
                if (engagementMetricsCtx) {
                    new Chart(engagementMetricsCtx.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: ['Engaged', 'Not Engaged'],
                            datasets: [{
                                data: [@json($participantsData['engagementMetrics']['engaged']), @json($participantsData['engagementMetrics']['total'] - $participantsData['engagementMetrics']['engaged'])],
                                backgroundColor: [
                                    'rgba(59, 130, 246, 0.8)',
                                    'rgba(147, 51, 234, 0.8)'
                                ],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    });
                }

                const engagementRateCtx = document.getElementById('engagementRateChart');
                if (engagementRateCtx) {
                    new Chart(engagementRateCtx.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: ['Engagement Rate', 'Remaining'],
                            datasets: [{
                                data: [@json($participantsData['engagementMetrics']['engagement_rate']), 100 - @json($participantsData['engagementMetrics']['engagement_rate'])],
                                backgroundColor: [
                                    'rgba(147, 51, 234, 0.8)',
                                    'rgba(59, 130, 246, 0.8)'
                                ],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    });
                }
            });
        </script>
        @endpush

        <!-- Header -->
        <div class="bg-white border-b border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-blue-600 rounded flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <h1 class="text-xl font-semibold text-blue-900">Performance Dashboard Facebook Group Insight</h1>
                </div>
                
                <!-- Time Filter -->
                <div class="flex space-x-1 bg-gray-100 rounded-lg p-1">
                    <button class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md">Weekly</button>
                    <button class="px-4 py-2 text-sm font-medium text-blue-600 hover:bg-gray-200 rounded-md">Monthly</button>
                    <button class="px-4 py-2 text-sm font-medium text-blue-600 hover:bg-gray-200 rounded-md">Yearly</button>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="bg-white border-b border-gray-200 px-6 py-3">
            <div class="flex space-x-1">
                <a href="?tab=growth" class="flex items-center space-x-2 px-4 py-2 text-sm font-medium rounded-md {{ $activeTab == 'growth' ? 'text-white bg-blue-600' : 'text-blue-600 hover:bg-gray-100' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    <span>Growth</span>
                </a>
                <a href="?tab=engagement" class="flex items-center space-x-2 px-4 py-2 text-sm font-medium rounded-md {{ $activeTab == 'engagement' ? 'text-white bg-blue-600' : 'text-blue-600 hover:bg-gray-100' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"></path>
                    </svg>
                    <span>Engagement</span>
                </a>
                <a href="?tab=participants" class="flex items-center space-x-2 px-4 py-2 text-sm font-medium rounded-md {{ $activeTab == 'participants' ? 'text-white bg-blue-600' : 'text-blue-600 hover:bg-gray-100' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <span>Participants</span>
                </a>
                <a href="?tab=data-raw" class="flex items-center space-x-2 px-4 py-2 text-sm font-medium rounded-md {{ $activeTab == 'data-raw' ? 'text-white bg-blue-600' : 'text-blue-600 hover:bg-gray-100' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <span>Data Raw</span>
                </a>
            </div>
        </div>

        <div class="p-6 space-y-6">
            @if($activeTab == 'growth')
                @include('dashboard.tabs.growth')
            @elseif($activeTab == 'engagement')
                @include('dashboard.tabs.engagement')
            @elseif($activeTab == 'participants')
                @include('dashboard.tabs.participants')
            @elseif($activeTab == 'data-raw')
                @include('dashboard.tabs.data-raw')
            @endif
        </div>
    </div>
</x-layouts.app>
