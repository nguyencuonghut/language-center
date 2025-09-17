<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Faker\Factory as FakerFactory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Danh sách họ, tên đệm, tên phổ biến Việt Nam
        $ho = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Phan', 'Vũ', 'Đặng', 'Bùi', 'Đỗ', 'Hồ', 'Ngô', 'Dương', 'Lý'];
        $dem = ['Văn', 'Hữu', 'Đức', 'Minh', 'Gia', 'Quang', 'Xuân', 'Ngọc', 'Thanh', 'Tuấn', 'Anh', 'Trung', 'Thuỳ', 'Thu', 'Mai', 'Hải', 'Kim'];
        $ten = ['Nam', 'Hùng', 'Dũng', 'Sơn', 'Long', 'Hà', 'Trang', 'Lan', 'Hương', 'Linh', 'Ngọc', 'Phương', 'Quang', 'Tuấn', 'Anh', 'Thảo', 'Vy', 'Khoa', 'Bảo', 'Khánh', 'Tú', 'Hiếu', 'Phúc', 'Thịnh', 'Tâm', 'Châu', 'Yến', 'Nhung', 'Hạnh', 'Loan'];

        $name = $this->faker->randomElement($ho) . ' ' .
                $this->faker->randomElement($dem) . ' ' .
                $this->faker->randomElement($ten);

        return [
            'code'    => 'STU'.$this->faker->unique()->numberBetween(10000,99999).$this->faker->randomElement(['A','B','C']),
            'name'    => $name,
            'gender'  => $this->faker->randomElement(['Nam','Nữ','Khác']),
            'dob'     => $this->faker->dateTimeBetween('-16 years', '-6 years'),
            'email'   => $this->faker->unique()->safeEmail(),
            'phone'   => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'active'  => true,
        ];
    }
}
