<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $activeTab = $request->get('tab', 'growth');

        // Weekly KPI Data (Growth tab)
        $weeklyKpis = [
            'join' => [
                'value' => 28,
                'percentage' => 56.0,
                'target' => 50,
                'progress' => 56.0
            ],
            'view' => [
                'value' => 387,
                'percentage' => 51.6,
                'target' => 750,
                'progress' => 51.6
            ],
            'engagement' => [
                'value' => 22,
                'percentage' => 58.7,
                'target' => 37.5,
                'progress' => 58.7
            ]
        ];

        // Detailed metrics with 4 days ago comparison (Growth tab)
        $detailedMetrics = [
            'joined' => [
                'value' => 28,
                'change' => 13,
                'trend' => 'up',
                'icon' => 'users'
            ],
            'no_active' => [
                'value' => 35,
                'change' => -19,
                'trend' => 'down',
                'icon' => 'user-group'
            ],
            'posts' => [
                'value' => 32,
                'change' => -19,
                'trend' => 'down',
                'icon' => 'document-text'
            ],
            'viewed' => [
                'value' => 387,
                'change' => -244,
                'trend' => 'down',
                'icon' => 'eye'
            ],
            'reactions' => [
                'value' => 12,
                'change' => -9,
                'trend' => 'down',
                'icon' => 'heart'
            ],
            'comments' => [
                'value' => 10,
                'change' => 1,
                'trend' => 'up',
                'icon' => 'chat-bubble-left-right'
            ]
        ];

        // Chart data for combined column and line chart (Growth tab)
        $chartData = [
            'labels' => ['thg 8 1', 'thg 8 2', 'thg 8 3', 'thg 8 4'],
            'joined' => [8, 3.5, 4, 8.5],
            'posts' => [7.5, 4.5, 7, 9.5],
            'engagements' => [7.5, 3.5, 8, 9]
        ];

        // Weekly data table (Growth tab)
        $weeklyData = [
            [
                'date' => '4 thg 8, 2025',
                'status' => 'pass KPI',
                'kpi' => 7,
                'joined' => 10,
                'no_active' => 11,
                'posts' => 11,
                'viewed' => 102,
                'reactions' => 3,
                'comments' => 3
            ],
            [
                'date' => '3 thg 8, 2025',
                'status' => 'fail KPI',
                'kpi' => 7,
                'joined' => 5,
                'no_active' => 10,
                'posts' => 8,
                'viewed' => 82,
                'reactions' => 4,
                'comments' => 4
            ],
            [
                'date' => '2 thg 8, 2025',
                'status' => 'fail KPI',
                'kpi' => 7,
                'joined' => 4,
                'no_active' => 5,
                'posts' => 5,
                'viewed' => 64,
                'reactions' => 0,
                'comments' => 0
            ],
            [
                'date' => '1 thg 8, 2025',
                'status' => 'pass KPI',
                'kpi' => 7,
                'joined' => 9,
                'no_active' => 9,
                'posts' => 8,
                'viewed' => 139,
                'reactions' => 5,
                'comments' => 3
            ]
        ];

        // Calculate totals (Growth tab)
        $totals = [
            'kpi' => 7,
            'joined' => 28,
            'no_active' => 35,
            'posts' => 32,
            'viewed' => 387,
            'reactions' => 12,
            'comments' => 10
        ];

        // Engagement tab data
        $engagementData = [
            'posts' => [
                'value' => 214,
                'change' => -18.8,
                'trend' => 'down'
            ],
            'engagement' => [
                'value' => 107,
                'change' => -18.8,
                'trend' => 'down'
            ],
            'chartData' => [
                'labels' => ['17 thg 7', '18 thg 7', '19 thg 7', '20 thg 7', '21 thg 7', '22 thg 7', '23 thg 7', '24 thg 7', '25 thg 7', '26 thg 7', '27 thg 7', '28 thg 7', '29 thg 7', '30 thg 7', '31 thg 7', '1 thg 8', '2 thg 8', '3 thg 8', '4 thg 8', '5 thg 8', '6 thg 8', '7 thg 8', '8 thg 8', '9 thg 8', '10 thg 8', '11 thg 8', '12 thg 8', '13 thg 8'],
                'posts' => [15, 12, 18, 14, 16, 13, 17, 15, 19, 16, 14, 18, 15, 17, 16, 14, 18, 15, 17, 16, 14, 18, 15, 17, 16, 14, 18, 15],
                'posts_28_days_ago' => [18, 15, 21, 17, 19, 16, 20, 18, 22, 19, 17, 21, 18, 20, 19, 17, 21, 18, 20, 19, 17, 21, 18, 20, 19, 17, 21, 18],
                'engagement' => [8, 6, 10, 7, 9, 6, 8, 7, 10, 8, 7, 9, 8, 10, 9, 7, 9, 8, 10, 9, 7, 9, 8, 10, 9, 7, 9, 8],
                'engagement_28_days_ago' => [10, 8, 12, 9, 11, 8, 10, 9, 12, 10, 9, 11, 10, 12, 11, 9, 11, 10, 12, 11, 9, 11, 10, 12, 11, 9, 11, 10]
            ],
            'topPosts' => [
                ['member' => 'Nhu Quyen Dao', 'posts' => 'How to improve sales', 'category' => 'Others', 'reactions' => 5, 'comments' => 2, 'views' => 280, 'er' => 1.00],
                ['member' => 'Nguyen Van A', 'posts' => 'Sales tips', 'category' => 'Sales', 'reactions' => 8, 'comments' => 3, 'views' => 245, 'er' => 1.20],
                ['member' => 'Tran Thi B', 'posts' => 'Team meeting', 'category' => 'Manager', 'reactions' => 12, 'comments' => 5, 'views' => 198, 'er' => 1.50],
                ['member' => 'Le Van C', 'posts' => 'Product update', 'category' => 'Others', 'reactions' => 3, 'comments' => 1, 'views' => 156, 'er' => 0.80],
                ['member' => 'Pham Thi D', 'posts' => 'Customer feedback', 'category' => 'Others', 'reactions' => 7, 'comments' => 4, 'views' => 134, 'er' => 1.10],
                ['member' => 'Hoang Van E', 'posts' => 'Marketing strategy', 'category' => 'Manager', 'reactions' => 15, 'comments' => 8, 'views' => 123, 'er' => 2.00],
                ['member' => 'Vu Thi F', 'posts' => 'Weekly report', 'category' => 'Others', 'reactions' => 4, 'comments' => 2, 'views' => 98, 'er' => 0.90],
                ['member' => 'Do Van G', 'posts' => 'Training session', 'category' => 'Manager', 'reactions' => 9, 'comments' => 6, 'views' => 87, 'er' => 1.30],
                ['member' => 'Bui Thi H', 'posts' => 'New product launch', 'category' => 'Sales', 'reactions' => 11, 'comments' => 7, 'views' => 76, 'er' => 1.40],
                ['member' => 'Dang Van I', 'posts' => 'Team building', 'category' => 'Others', 'reactions' => 6, 'comments' => 3, 'views' => 65, 'er' => 1.00]
            ],
            'postsByCategory' => [
                ['category' => 'Others', 'posts' => 40, 'likes' => 24.0, 'er' => 0.16],
                ['category' => 'Manager', 'posts' => 6, 'likes' => 3.6, 'er' => 0.02],
                ['category' => 'Sales', 'posts' => 2, 'likes' => 1.2, 'er' => 0.02]
            ],
            'topContributors' => [
                'posts' => [
                    ['name' => 'Ung Quynh', 'value' => 11, 'percentage' => 0.07],
                    ['name' => 'Nguyen Van A', 'value' => 9, 'percentage' => 0.06],
                    ['name' => 'Tran Thi B', 'value' => 8, 'percentage' => 0.05],
                    ['name' => 'Le Van C', 'value' => 7, 'percentage' => 0.04],
                    ['name' => 'Pham Thi D', 'value' => 6, 'percentage' => 0.04],
                    ['name' => 'Hoang Van E', 'value' => 5, 'percentage' => 0.03],
                    ['name' => 'Vu Thi F', 'value' => 5, 'percentage' => 0.03],
                    ['name' => 'Do Van G', 'value' => 4, 'percentage' => 0.03],
                    ['name' => 'Bui Thi H', 'value' => 4, 'percentage' => 0.03],
                    ['name' => 'Dang Van I', 'value' => 3, 'percentage' => 0.02]
                ],
                'likes' => [
                    ['name' => 'Ung Quynh', 'value' => 17894, 'percentage' => 17.89],
                    ['name' => 'Nguyen Van A', 'value' => 15432, 'percentage' => 15.43],
                    ['name' => 'Tran Thi B', 'value' => 12345, 'percentage' => 12.35],
                    ['name' => 'Le Van C', 'value' => 9876, 'percentage' => 9.88],
                    ['name' => 'Pham Thi D', 'value' => 8765, 'percentage' => 8.77],
                    ['name' => 'Hoang Van E', 'value' => 7654, 'percentage' => 7.65],
                    ['name' => 'Vu Thi F', 'value' => 6543, 'percentage' => 6.54],
                    ['name' => 'Do Van G', 'value' => 5432, 'percentage' => 5.43],
                    ['name' => 'Bui Thi H', 'value' => 4321, 'percentage' => 4.32],
                    ['name' => 'Dang Van I', 'value' => 3210, 'percentage' => 3.21]
                ],
                'comments' => [
                    ['name' => 'Ung Quynh', 'value' => 18754, 'percentage' => 18.75],
                    ['name' => 'Nguyen Van A', 'value' => 16234, 'percentage' => 16.23],
                    ['name' => 'Tran Thi B', 'value' => 13456, 'percentage' => 13.46],
                    ['name' => 'Le Van C', 'value' => 10876, 'percentage' => 10.88],
                    ['name' => 'Pham Thi D', 'value' => 9765, 'percentage' => 9.77],
                    ['name' => 'Hoang Van E', 'value' => 8654, 'percentage' => 8.65],
                    ['name' => 'Vu Thi F', 'value' => 7543, 'percentage' => 7.54],
                    ['name' => 'Do Van G', 'value' => 6432, 'percentage' => 6.43],
                    ['name' => 'Bui Thi H', 'value' => 5321, 'percentage' => 5.32],
                    ['name' => 'Dang Van I', 'value' => 4210, 'percentage' => 4.21]
                ]
            ],
            'activeUsers' => [
                'byDay' => [
                    ['day' => 'Monday', 'active' => 17, 'inactive' => 83],
                    ['day' => 'Tuesday', 'active' => 22, 'inactive' => 78],
                    ['day' => 'Wednesday', 'active' => 19, 'inactive' => 81],
                    ['day' => 'Thursday', 'active' => 25, 'inactive' => 75],
                    ['day' => 'Friday', 'active' => 28, 'inactive' => 72],
                    ['day' => 'Saturday', 'active' => 15, 'inactive' => 85],
                    ['day' => 'Sunday', 'active' => 12, 'inactive' => 88]
                ],
                'byHour' => [
                    ['hour' => '18h', 'active' => 14, 'inactive' => 86],
                    ['hour' => '19h', 'active' => 18, 'inactive' => 82],
                    ['hour' => '20h', 'active' => 22, 'inactive' => 78],
                    ['hour' => '21h', 'active' => 25, 'inactive' => 75],
                    ['hour' => '22h', 'active' => 20, 'inactive' => 80],
                    ['hour' => '23h', 'active' => 15, 'inactive' => 85],
                    ['hour' => '00h', 'active' => 8, 'inactive' => 92],
                    ['hour' => '01h', 'active' => 5, 'inactive' => 95],
                    ['hour' => '02h', 'active' => 3, 'inactive' => 97],
                    ['hour' => '03h', 'active' => 2, 'inactive' => 98],
                    ['hour' => '04h', 'active' => 1, 'inactive' => 99],
                    ['hour' => '05h', 'active' => 2, 'inactive' => 98],
                    ['hour' => '06h', 'active' => 4, 'inactive' => 96],
                    ['hour' => '07h', 'active' => 6, 'inactive' => 94],
                    ['hour' => '08h', 'active' => 12, 'inactive' => 88],
                    ['hour' => '09h', 'active' => 16, 'inactive' => 84],
                    ['hour' => '10h', 'active' => 19, 'inactive' => 81],
                    ['hour' => '11h', 'active' => 21, 'inactive' => 79]
                ]
            ],
            'heatmapData' => [
                // Monday to Sunday, 08h to 23h
                ['day' => 'Monday', '08h' => 3, '09h' => 4, '10h' => 2, '11h' => 1, '12h' => 2, '13h' => 3, '14h' => 2, '15h' => 1, '16h' => 2, '17h' => 3, '18h' => 4, '19h' => 4, '20h' => 3, '21h' => 2, '22h' => 1, '23h' => 1],
                ['day' => 'Tuesday', '08h' => 2, '09h' => 3, '10h' => 4, '11h' => 3, '12h' => 2, '13h' => 3, '14h' => 4, '15h' => 3, '16h' => 2, '17h' => 3, '18h' => 4, '19h' => 3, '20h' => 2, '21h' => 3, '22h' => 2, '23h' => 1],
                ['day' => 'Wednesday', '08h' => 3, '09h' => 2, '10h' => 3, '11h' => 4, '12h' => 3, '13h' => 2, '14h' => 3, '15h' => 4, '16h' => 3, '17h' => 2, '18h' => 3, '19h' => 4, '20h' => 3, '21h' => 2, '22h' => 1, '23h' => 1],
                ['day' => 'Thursday', '08h' => 4, '09h' => 3, '10h' => 2, '11h' => 3, '12h' => 4, '13h' => 3, '14h' => 2, '15h' => 3, '16h' => 4, '17h' => 3, '18h' => 2, '19h' => 3, '20h' => 4, '21h' => 3, '22h' => 2, '23h' => 1],
                ['day' => 'Friday', '08h' => 3, '09h' => 4, '10h' => 3, '11h' => 2, '12h' => 3, '13h' => 4, '14h' => 3, '15h' => 4, '16h' => 3, '17h' => 2, '18h' => 3, '19h' => 4, '20h' => 3, '21h' => 2, '22h' => 1, '23h' => 1],
                ['day' => 'Saturday', '08h' => 4, '09h' => 3, '10h' => 2, '11h' => 1, '12h' => 2, '13h' => 3, '14h' => 2, '15h' => 1, '16h' => 2, '17h' => 3, '18h' => 2, '19h' => 1, '20h' => 2, '21h' => 1, '22h' => 1, '23h' => 0],
                ['day' => 'Sunday', '08h' => 2, '09h' => 1, '10h' => 2, '11h' => 1, '12h' => 1, '13h' => 2, '14h' => 1, '15h' => 1, '16h' => 2, '17h' => 1, '18h' => 1, '19h' => 2, '20h' => 1, '21h' => 1, '22h' => 0, '23h' => 0]
            ]
        ];

        // Participants tab data
        $participantsData = [
            'ageGender' => [
                'labels' => ['13-17', '18-24', '25-34', '35-44', '45-54', '55-64', '65+'],
                'women' => [1, 19, 28, 12, 7, 3, 1],
                'men' => [1, 11, 25, 24, 8, 4, 2]
            ],
            'engagementMetrics' => [
                'total' => 1880,
                'engaged' => 1080,
                'engagement_rate' => 54.4
            ],
            'countries' => [
                ['country' => 'Vietnam', 'members' => 3330, 'percentage' => 99.7],
                ['country' => 'Myanmar', 'members' => 4, 'percentage' => 0.1],
                ['country' => 'United States', 'members' => 1, 'percentage' => 0.0],
                ['country' => 'Poland', 'members' => 1, 'percentage' => 0.0],
                ['country' => 'Thailand', 'members' => 1, 'percentage' => 0.0],
                ['country' => 'Bangladesh', 'members' => 1, 'percentage' => 0.0]
            ],
            'cities' => [
                ['city' => 'Ho Chi Minh City, Vietnam', 'members' => 973, 'percentage' => 48.7],
                ['city' => 'Can Tho, Can Tho, Vietnam', 'members' => 147, 'percentage' => 7.4],
                ['city' => 'Hanoi, Vietnam', 'members' => 103, 'percentage' => 5.2],
                ['city' => 'My Tho, Tien Giang, Vietnam', 'members' => 49, 'percentage' => 2.5],
                ['city' => 'Vinh Long, Vinh Long, Vietnam', 'members' => 45, 'percentage' => 2.3],
                ['city' => 'Lang Xuyen, An Giang, Vietnam', 'members' => 33, 'percentage' => 1.7],
                ['city' => 'Ca Mau', 'members' => 33, 'percentage' => 1.7],
                ['city' => 'Sa Dec, Dong Thap, Vietnam', 'members' => 32, 'percentage' => 1.6],
                ['city' => 'Bac Giang, Bac Giang, Vietnam', 'members' => 32, 'percentage' => 1.6],
                ['city' => 'Bac Lieu, Bac Lieu, Vietnam', 'members' => 31, 'percentage' => 1.6]
            ]
        ];

        return view('dashboard', compact(
            'activeTab',
            'weeklyKpis', 
            'detailedMetrics', 
            'chartData', 
            'weeklyData', 
            'totals',
            'engagementData',
            'participantsData'
        ));
    }
} 