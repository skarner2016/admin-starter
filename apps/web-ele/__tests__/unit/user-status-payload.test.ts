import { describe, expect, it } from 'vitest';

import { buildUserStatusTogglePayload } from '../../src/views/system/user/payload';

describe('buildUserStatusTogglePayload', () => {
  it('keeps the avatar so toggling status cannot wipe it', () => {
    const payload = buildUserStatusTogglePayload(
      {
        avatar: '/storage/uploads/avatar.png',
        deptId: '5',
        description: '前端组',
        id: '3',
        realName: 'Jack',
        roles: ['user'],
        status: 1,
        username: 'jack',
      },
      0,
    );

    expect(payload).toMatchObject({
      avatar: '/storage/uploads/avatar.png',
      deptId: '5',
      description: '前端组',
      realName: 'Jack',
      roles: ['user'],
      status: 0,
      username: 'jack',
    });
  });

  it('leaves the avatar out instead of sending an empty string', () => {
    const payload = buildUserStatusTogglePayload(
      {
        avatar: '',
        id: '3',
        realName: 'Jack',
        roles: [],
        status: 1,
        username: 'jack',
      },
      1,
    );

    expect(payload.avatar).toBeUndefined();
    expect(payload.deptId).toBe('');
    expect(payload.roles).toEqual([]);
  });
});
