import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 40 40"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                d="M9 28V20M16 28V15M23 28V22M30 28V11"
                stroke="currentColor"
                strokeWidth="3.8"
                strokeLinecap="round"
            />
        </svg>
    );
}
